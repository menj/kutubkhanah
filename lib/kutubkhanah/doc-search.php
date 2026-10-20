<?php
/**
 * Kutubkhanah: "search inside books" (after PMB's full-text search of digital copies).
 * PDF text is read with pdftotext; pages with no text layer are read with tesseract (OCR) when
 * it is installed. The text is kept page by page in kk_doc_text, so results show the page.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class DocSearch
{
    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    public static function tools(): array
    {
        $has = function (string $bin): bool {
            if (!function_exists('exec')) return false;
            $out = []; $rc = 1;
            @exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $rc);
            return $rc === 0 && !empty($out[0]);
        };
        return ['pdftotext' => $has('pdftotext'), 'pdftoppm' => $has('pdftoppm'), 'tesseract' => $has('tesseract')];
    }

    /** Digital copies attached to books, with their index state. */
    public static function files(): array
    {
        return self::db()->query(
            "SELECT f.file_id, f.file_title, f.file_name, f.file_dir, f.mime_type, a.biblio_id, b.title,
                    d.pages, d.method, d.status, d.indexed_at, d.file_mtime
             FROM files f JOIN biblio_attachment a ON a.file_id = f.file_id JOIN biblio b ON b.biblio_id = a.biblio_id
             LEFT JOIN kk_doc_index d ON d.file_id = f.file_id
             WHERE f.mime_type = 'application/pdf' OR LOWER(f.file_name) LIKE '%.pdf'
             ORDER BY b.title, f.file_title"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function path(array $f): string
    {
        $dir = trim((string)$f['file_dir'], '/\\');
        return rtrim(REPOBS, '/\\') . DIRECTORY_SEPARATOR . ($dir !== '' ? $dir . DIRECTORY_SEPARATOR : '') . $f['file_name'];
    }

    /** Files that are new or changed since they were indexed. */
    public static function pending(): array
    {
        $out = [];
        foreach (self::files() as $f) {
            $p = self::path($f);
            if (!is_file($p)) continue;
            if ($f['status'] === null || (int)$f['file_mtime'] !== (int)filemtime($p)) $out[] = (int)$f['file_id'];
        }
        return $out;
    }

    public static function counts(): array
    {
        $files = self::files();
        $done = count(array_filter($files, fn($f) => $f['status'] === 'done'));
        $pages = (int)self::db()->query('SELECT COUNT(*) FROM kk_doc_text')->fetchColumn();
        return ['files' => count($files), 'indexed' => $done, 'pages' => $pages];
    }

    /** Index one file. @return array{pages:int,method:string,status:string} */
    public static function index(int $fileId, bool $ocr = true): array
    {
        $st = self::db()->prepare(
            'SELECT f.*, a.biblio_id FROM files f JOIN biblio_attachment a ON a.file_id = f.file_id WHERE f.file_id = ? LIMIT 1'
        );
        $st->execute([$fileId]);
        $f = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$f) throw new \InvalidArgumentException(__('That file is not attached to a book.'));
        $path = self::path($f);
        if (!is_file($path)) throw new \InvalidArgumentException(__('The file is missing from the repository.'));
        $tools = self::tools();
        if (!$tools['pdftotext']) throw new \InvalidArgumentException(__('pdftotext (poppler-utils) is not installed on this server.'));
        @set_time_limit(600);

        $pages = self::extract($path);
        $method = 'text';
        if ($ocr && $tools['tesseract'] && $tools['pdftoppm']) {
            foreach ($pages as $n => $txt) {
                if (mb_strlen(trim($txt)) < 20) {
                    $o = self::ocrPage($path, $n);
                    if ($o !== '') { $pages[$n] = $o; $method = 'ocr'; }
                }
            }
        }

        $db = self::db();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM kk_doc_text WHERE file_id = ?')->execute([$fileId]);
            $ins = $db->prepare('INSERT INTO kk_doc_text (file_id, page, biblio_id, content) VALUES (?, ?, ?, ?)');
            $kept = 0;
            foreach ($pages as $n => $txt) {
                $txt = trim(preg_replace('/[ \t]+/', ' ', $txt));
                if ($txt === '') continue;
                $ins->execute([$fileId, $n, (int)$f['biblio_id'], $txt]);
                $kept++;
            }
            $db->prepare('DELETE FROM kk_doc_index WHERE file_id = ?')->execute([$fileId]);
            $status = $kept > 0 ? 'done' : 'empty';
            $db->prepare('INSERT INTO kk_doc_index (file_id, biblio_id, pages, method, status, file_mtime, indexed_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$fileId, (int)$f['biblio_id'], count($pages), $method, $status, (int)filemtime($path), date('Y-m-d H:i:s')]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['pages' => $kept, 'method' => $method, 'status' => $status];
    }

    public static function forget(int $fileId): void
    {
        self::db()->prepare('DELETE FROM kk_doc_text WHERE file_id = ?')->execute([$fileId]);
        self::db()->prepare('DELETE FROM kk_doc_index WHERE file_id = ?')->execute([$fileId]);
    }

    /** Page texts, 1-based. pdftotext separates pages with a form feed. */
    private static function extract(string $path): array
    {
        $out = [];
        $rc = 1;
        @exec('pdftotext -enc UTF-8 -layout ' . escapeshellarg($path) . ' - 2>/dev/null', $out, $rc);
        if ($rc !== 0) return [];
        $text = implode("\n", $out);
        $parts = explode("\f", $text);
        if (end($parts) !== false && trim((string)end($parts)) === '') array_pop($parts);
        $pages = [];
        foreach ($parts as $i => $t) $pages[$i + 1] = $t;
        return $pages;
    }

    private static function ocrPage(string $path, int $page): string
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kk-ocr-' . bin2hex(random_bytes(6));
        $lang = preg_replace('/[^a-z+_]/i', '', (string)Settings::get('ocr_lang')) ?: 'eng';
        @exec('pdftoppm -r 200 -gray -png -f ' . $page . ' -l ' . $page . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($tmp) . ' 2>/dev/null');
        $img = glob($tmp . '*.png') ?: [];
        $text = '';
        if ($img) {
            $o = [];
            @exec('tesseract ' . escapeshellarg($img[0]) . ' stdout -l ' . escapeshellarg($lang) . ' 2>/dev/null', $o);
            $text = implode("\n", $o);
        }
        foreach ($img as $i) @unlink($i);
        return trim($text);
    }

    /* ---------- Search ---------- */

    private static function terms(string $q): array
    {
        preg_match_all('/"([^"]+)"|(\S+)/u', $q, $m, PREG_SET_ORDER);
        $t = [];
        foreach ($m as $x) {
            $w = trim($x[1] !== '' ? $x[1] : ($x[2] ?? ''));
            if (mb_strlen($w) >= 2) $t[] = $w;
        }
        return array_slice(array_unique($t), 0, 6);
    }

    /** Pages containing every term, grouped by book, with a snippet. */
    public static function search(string $q, int $limit = 40): array
    {
        $terms = self::terms($q);
        if (!$terms) return [];
        $where = []; $args = [];
        foreach ($terms as $t) {
            $where[] = 't.content LIKE ?';
            $args[] = '%' . addcslashes($t, '%_\\') . '%';
        }
        $st = self::db()->prepare(
            'SELECT t.file_id, t.page, t.content, b.biblio_id, b.title, b.image, f.file_title
             FROM kk_doc_text t JOIN biblio b ON b.biblio_id = t.biblio_id JOIN files f ON f.file_id = t.file_id
             WHERE b.opac_hide = 0 AND EXISTS (SELECT 1 FROM biblio_attachment a WHERE a.file_id = t.file_id AND a.access_type = \'public\') AND ' . implode(' AND ', $where) . ' ORDER BY b.title, t.page LIMIT ' . (int)$limit
        );
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $r['snippet'] = self::snippet((string)$r['content'], $terms);
            unset($r['content']);
            $out[(int)$r['biblio_id']]['title'] = $r['title'];
            $out[(int)$r['biblio_id']]['image'] = $r['image'];
            $out[(int)$r['biblio_id']]['hits'][] = $r;
        }
        return $out;
    }

    /** Escaped text around the first term, with the terms in <mark>. */
    private static function snippet(string $text, array $terms): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        $pos = null;
        foreach ($terms as $t) {
            $p = mb_stripos($text, $t);
            if ($p !== false && ($pos === null || $p < $pos)) $pos = $p;
        }
        $start = max(0, ($pos ?? 0) - 80);
        $part = mb_substr($text, $start, 240);
        $html = htmlspecialchars(($start > 0 ? '… ' : '') . $part . (mb_strlen($text) > $start + 240 ? ' …' : ''), ENT_QUOTES, 'UTF-8');
        foreach ($terms as $t) {
            $html = preg_replace('/(' . preg_quote(htmlspecialchars($t, ENT_QUOTES, 'UTF-8'), '/') . ')/iu', '<mark>$1</mark>', $html) ?? $html;
        }
        return $html;
    }

    public static function page(string $q): string
    {
        $e = [Library::class, 'e'];
        $form = '<form class="kk-inside__form" method="get" action="index.php"><input type="hidden" name="p" value="inside">'
            . '<input class="kk-input" type="search" name="q" value="' . $e($q) . '" placeholder="' . $e(__('Words or a phrase inside your digital books')) . '" aria-label="' . $e(__('Search inside books')) . '">'
            . '<button class="kk-btn kk-btn--primary" type="submit">' . __('Search') . '</button></form>';
        if (trim($q) === '') {
            $n = self::counts();
            return $form . '<p class="kk-muted">' . $e(sprintf(__('Searches the text of %d digital books.'), $n['indexed'])) . '</p>';
        }
        $res = self::search($q);
        if (!$res) return $form . '<p class="kk-muted">' . __('Nothing found in the digital books.') . '</p>';
        $out = $form . '<ul class="kk-inside">';
        foreach ($res as $id => $b) {
            $out .= '<li class="kk-inside__book"><a class="kk-inside__title" href="index.php?p=show_detail&amp;id=' . (int)$id . '" dir="auto">' . $e($b['title']) . '</a><ul>';
            foreach ($b['hits'] as $h) {
                $out .= '<li><a href="' . SWB . 'index.php?p=fstream-pdf&amp;fid=' . (int)$h['file_id'] . '&amp;bid=' . (int)$id . '#page=' . (int)$h['page'] . '" target="_blank" rel="noopener">'
                    . $e(sprintf(__('Page %d'), (int)$h['page'])) . '</a> <span dir="auto">' . $h['snippet'] . '</span></li>';
            }
            $out .= '</ul></li>';
        }
        return $out . '</ul>';
    }
}
