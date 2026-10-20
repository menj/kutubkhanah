<?php
/**
 * Kutubkhanah: Libib CSV import and export.
 * Books are matched by ISBN (or exact title when there is no ISBN), so re-importing is safe.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Importer
{
    /** @return array{created:int,updated:int,skipped:int} */
    public static function importFile(string $path): array
    {
        @set_time_limit(600);
        $fh = fopen($path, 'r');
        if (!$fh) throw new \RuntimeException(__('The file could not be read.'));
        $head = fgetcsv($fh, null, ',', '"', '');
        if (!$head) { fclose($fh); throw new \RuntimeException(__('The CSV is empty.')); }
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$head[0]);
        $head = array_map(fn($h) => strtolower(trim((string)$h)), $head);
        if (!in_array('title', $head, true)) {
            fclose($fh);
            throw new \RuntimeException(__('This does not look like a Libib export (no "title" column).'));
        }

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $created = [];
        while (($row = fgetcsv($fh, null, ',', '"', '')) !== false) {
            if (count($row) < 2) continue;
            $row = array_combine($head, array_pad(array_slice($row, 0, count($head)), count($head), ''));
            [$result, $id] = self::importRow($row);
            $counts[$result]++;
            if ($result === 'created') $created[] = $id;
        }
        fclose($fh);
        foreach ($created as $id) Books::reindex($id);
        return $counts;
    }

    private static function status(string $s): string
    {
        $s = str_replace([' ', '-'], '_', strtolower(trim($s)));
        $alias = ['unread' => 'not_begun', 'reading' => 'in_progress', 'read' => 'completed'];
        $s = $alias[$s] ?? $s;
        return in_array($s, ['not_begun', 'in_progress', 'completed'], true) ? $s : '';
    }

    /** @return array{0:string,1:int} [created|skipped, biblio_id] */
    private static function importRow(array $row): array
    {
        $g = fn($k) => trim((string)($row[$k] ?? ''));
        $title = mb_substr(strip_tags($g('title')), 0, 500);
        $i13 = Isbn::clean($g('ean_isbn13'));
        $i10 = Isbn::clean($g('upc_isbn10'));
        if ($i13 === '' && strlen($i10) === 10 && Isbn::isValid10($i10)) $i13 = Isbn::to13($i10);
        if ($title === '' && $i13 === '') return ['skipped', 0];

        // Match by ISBN; a malformed ISBN-10 by its raw text; no ISBN at all by exact title.
        if ($i13 !== '') {
            $existing = Books::findByIsbn($i13);
        } elseif ($i10 !== '') {
            $existing = Books::findByRawIsbn($i10);
        } else {
            $existing = Books::findByTitleWithoutIsbn($title);
        }
        if ($existing) return ['skipped', $existing];

        $added = $g('added');
        $id = Books::create([
            'title'          => $title !== '' ? $title : 'ISBN ' . $i13,
            'authors'        => $g('creators'),
            'isbn13'         => $i13,
            'isbn10'         => $i10,
            'publisher'      => $g('publisher'),
            'publish_date'   => $g('publish_date'),
            'pages'          => $g('length'),
            'description'    => strip_tags($g('description')),
            'needs_details'  => $title === '',
            'reading_status' => self::status($g('status')),
            'copies'         => max(1, (int)$g('copies')),
            'added'          => preg_match('/^\d{4}-\d{2}-\d{2}/', $added) ? substr($added, 0, 10) : '',
            'source'         => 'libib',
        ]);
        return ['created', $id];
    }

    /** Stream the whole catalogue as a Libib-compatible CSV. */
    public static function export(): void
    {
        $db = DB::getInstance();
        $labels = Settings::libibStatuses();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="kutubkhanah-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['item_type', 'title', 'creators', 'ean_isbn13', 'upc_isbn10', 'description', 'publisher', 'publish_date', 'length', 'status', 'added', 'copies'], ',', '"', '');
        $rows = $db->query(
            "SELECT b.biblio_id, b.title, b.isbn_issn, b.notes, b.publish_year, b.collation, DATE(b.input_date) AS added,
                    p.publisher_name, k.reading_status,
                    (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level, a.author_name SEPARATOR ', ')
                       FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) AS creators,
                    (SELECT COUNT(*) FROM item i WHERE i.biblio_id = b.biblio_id) AS copies
             FROM biblio b
             LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id
             LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             ORDER BY b.title"
        );
        while ($r = $rows->fetch(\PDO::FETCH_ASSOC)) {
            $i13 = Isbn::normalize($r['isbn_issn']);
            fputcsv($out, [
                'book', $r['title'], (string)$r['creators'], $i13, $i13 ? Isbn::to10($i13) : '',
                strip_tags((string)$r['notes']), (string)$r['publisher_name'], (string)$r['publish_year'],
                preg_match('/(\d+)/', (string)$r['collation'], $m) ? $m[1] : '',
                $labels[$r['reading_status'] ?? ''] ?? '', $r['added'], max(1, (int)$r['copies']),
            ], ',', '"', '');
        }
        fclose($out);
    }
}
