<?php
/**
 * Kutubkhanah: book records on top of the SLiMS catalogue tables
 * (biblio, mst_author, biblio_author, mst_publisher, item, search_biblio)
 * plus Kutubkhanah's own kk_book table (needs-details flag, reading status, cover state).
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Books
{
    private static function db(): \PDO
    {
        return DB::getInstance();
    }

    private static function uid(): int
    {
        return (int)($_SESSION['uid'] ?? 1);
    }

    /* ---------- Lookups ---------- */

    public static function findByIsbn(string $isbn13): int
    {
        $i10 = Isbn::to10($isbn13);
        $st = self::db()->prepare(
            "SELECT biblio_id FROM biblio WHERE REPLACE(REPLACE(isbn_issn,'-',''),' ','') IN (?, ?) LIMIT 1"
        );
        $st->execute([$isbn13, $i10 !== '' ? $i10 : $isbn13]);
        return (int)$st->fetchColumn();
    }

    /** Exact match on the stored ISBN text, for malformed ISBNs that cannot be normalised. */
    public static function findByRawIsbn(string $isbn): int
    {
        $st = self::db()->prepare("SELECT biblio_id FROM biblio WHERE REPLACE(REPLACE(isbn_issn,'-',''),' ','') = ? LIMIT 1");
        $st->execute([$isbn]);
        return (int)$st->fetchColumn();
    }

    /** Exact-title match among books without an ISBN (keeps re-imports from duplicating them). */
    public static function findByTitleWithoutIsbn(string $title): int
    {
        $st = self::db()->prepare("SELECT biblio_id FROM biblio WHERE title = ? AND (isbn_issn IS NULL OR isbn_issn = '') LIMIT 1");
        $st->execute([$title]);
        return (int)$st->fetchColumn();
    }

    public static function authorId(string $name): int
    {
        $name = mb_substr(trim($name), 0, 100);
        $st = self::db()->prepare('SELECT author_id FROM mst_author WHERE author_name = ? LIMIT 1');
        $st->execute([$name]);
        if ($id = (int)$st->fetchColumn()) return $id;
        $today = date('Y-m-d');
        // Read lastInsertId from the same handle: each DB::getInstance() call runs a query that resets it.
        $db = self::db();
        $db->prepare("INSERT INTO mst_author (author_name, authority_type, input_date, last_update) VALUES (?, 'p', ?, ?)")
            ->execute([$name, $today, $today]);
        return (int)$db->lastInsertId();
    }

    public static function publisherId(string $name): ?int
    {
        $name = mb_substr(trim($name), 0, 100);
        if ($name === '') return null;
        $st = self::db()->prepare('SELECT publisher_id FROM mst_publisher WHERE publisher_name = ? LIMIT 1');
        $st->execute([$name]);
        if ($id = (int)$st->fetchColumn()) return $id;
        $today = date('Y-m-d');
        $db = self::db();
        $db->prepare('INSERT INTO mst_publisher (publisher_name, input_date, last_update) VALUES (?, ?, ?)')
            ->execute([$name, $today, $today]);
        return (int)$db->lastInsertId();
    }

    private static function textGmdId(): ?int
    {
        static $id = false;
        if ($id === false) {
            $id = (int)self::db()->query("SELECT gmd_id FROM mst_gmd WHERE gmd_name = 'Text' LIMIT 1")->fetchColumn() ?: null;
        }
        return $id;
    }

    /** Split a Libib/lookup author string ("A, B, C") into names. */
    public static function splitAuthors($authors): array
    {
        if (is_array($authors)) return array_values(array_filter(array_map('trim', $authors)));
        return array_values(array_filter(array_map('trim', explode(',', (string)$authors))));
    }

    /* ---------- kk_book meta ---------- */

    public static function meta(int $id): array
    {
        $st = self::db()->prepare('SELECT * FROM kk_book WHERE biblio_id = ?');
        $st->execute([$id]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: [
            'biblio_id' => $id, 'needs_details' => 0, 'reading_status' => '', 'cover_url' => '',
            'cover_tried' => null, 'source' => '', 'added' => null, 'format' => '', 'volume' => '',
            'tags' => '', 'digital_link' => '', 'oclc' => '', 'google_books_id' => '', 'goodreads_id' => '',
            'reading_progress' => 0, 'notes' => '', 'notes_public' => 0,
        ];
    }

    public static function setMeta(int $id, array $kv): void
    {
        $allowed = ['needs_details', 'reading_status', 'cover_url', 'cover_tried', 'source', 'added',
            'format', 'volume', 'tags', 'digital_link', 'oclc', 'google_books_id', 'goodreads_id',
            'reading_progress', 'notes', 'notes_public'];
        $kv = array_intersect_key($kv, array_flip($allowed));
        if (!$kv) return;
        $cols = array_keys($kv);
        $sql = 'INSERT INTO kk_book (biblio_id, ' . implode(', ', $cols) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ') '
            . 'ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols));
        self::db()->prepare($sql)->execute(array_merge([$id], array_values($kv)));
    }

    /** Validate the extra fields edited on Admin > Kutubkhanah > Book details. */
    public static function sanitizeExtras(array $in): array
    {
        $t = fn($k, $n) => mb_substr(trim(strip_tags((string)($in[$k] ?? ''))), 0, $n);
        $url = function ($k) use ($in) {
            $u = trim((string)($in[$k] ?? ''));
            return ($u !== '' && preg_match('#^https?://#i', $u) && filter_var($u, FILTER_VALIDATE_URL)) ? mb_substr($u, 0, 500) : '';
        };
        $status = (string)($in['reading_status'] ?? '');
        $tags = implode(', ', array_slice(array_values(array_unique(array_filter(array_map('trim', explode(',', $t('tags', 255)))))), 0, 20));
        return [
            'format'           => in_array($in['format'] ?? '', Settings::formats(), true) ? $in['format'] : '',
            'volume'           => $t('volume', 20),
            'tags'             => $tags,
            'digital_link'     => $url('digital_link'),
            'oclc'             => preg_replace('/[^0-9]/', '', $t('oclc', 30)),
            'google_books_id'  => preg_replace('/[^A-Za-z0-9_-]/', '', $t('google_books_id', 30)),
            'goodreads_id'     => preg_replace('/[^0-9]/', '', $t('goodreads_id', 20)),
            'reading_status'   => array_key_exists($status, Settings::readingStatuses()) ? $status : '',
            'reading_progress' => max(0, min(100, (int)($in['reading_progress'] ?? 0))),
            'notes'            => mb_substr(trim(strip_tags((string)($in['notes'] ?? ''))), 0, 5000),
            'notes_public'     => empty($in['notes_public']) ? 0 : 1,
        ];
    }

    /* ---------- Create / update ---------- */

    /**
     * Create a catalogue record.
     * $d keys: title, authors (array|string), isbn13, isbn10, publisher, publish_date, pages,
     *          description, needs_details, reading_status, copies, added (Y-m-d), cover_url, source
     */
    public static function create(array $d): int
    {
        $now = date('Y-m-d H:i:s');
        $authors = self::splitAuthors($d['authors'] ?? []);
        $publisherId = self::publisherId((string)($d['publisher'] ?? ''));
        $added = !empty($d['added']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['added']) ? $d['added'] . ' 00:00:00' : $now;
        $year = preg_match('/(\d{4})/', (string)($d['publish_date'] ?? ''), $m) ? $m[1] : '';
        $isbn = (string)($d['isbn13'] ?? '') !== '' ? $d['isbn13'] : (string)($d['isbn10'] ?? '');
        $needs = !empty($d['needs_details']) ? 1 : 0;

        $db = self::db();
        $db->prepare(
            'INSERT INTO biblio (gmd_id, title, sor, isbn_issn, publisher_id, publish_year, collation, language_id, notes,
                                 opac_hide, promoted, input_date, last_update, uid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
        )->execute([
            self::textGmdId(),
            $d['title'],
            mb_substr(implode(', ', $authors), 0, 200),
            mb_substr($isbn, 0, 32),
            $publisherId,
            $year,
            !empty($d['pages']) ? (int)$d['pages'] . ' p.' : '',
            Settings::get('default_language') ?: 'en',
            (string)($d['description'] ?? ''),
            $needs,
            $added,
            $now,
            self::uid(),
        ]);
        $id = (int)$db->lastInsertId();

        self::attachAuthors($id, $authors);
        self::addItems($id, max(1, (int)($d['copies'] ?? 1)), substr($added, 0, 10));
        self::setMeta($id, [
            'needs_details'  => $needs,
            'reading_status' => (string)($d['reading_status'] ?? ''),
            'cover_url'      => (string)($d['cover_url'] ?? ''),
            'source'         => (string)($d['source'] ?? ''),
            'added'          => substr($added, 0, 10),
        ]);
        return $id;
    }

    public static function attachAuthors(int $id, array $authors): void
    {
        $st = self::db()->prepare('INSERT IGNORE INTO biblio_author (biblio_id, author_id, level) VALUES (?, ?, ?)');
        foreach (array_values($authors) as $i => $name) {
            $st->execute([$id, self::authorId($name), $i === 0 ? 1 : 2]);
        }
    }

    public static function copies(int $id): int
    {
        $st = self::db()->prepare('SELECT COUNT(*) FROM item WHERE biblio_id = ?');
        $st->execute([$id]);
        return (int)$st->fetchColumn();
    }

    public static function addItems(int $id, int $count, string $date): void
    {
        $now = date('Y-m-d H:i:s');
        $location = self::db()->query("SELECT location_id FROM mst_location ORDER BY location_id LIMIT 1")->fetchColumn() ?: null;
        $existing = self::copies($id);
        $st = self::db()->prepare(
            "INSERT INTO item (biblio_id, item_code, location_id, item_status_id, received_date, source, input_date, last_update, uid)
             VALUES (?, ?, ?, '0', ?, 1, ?, ?, ?)"
        );
        for ($n = $existing + 1; $n <= $existing + $count; $n++) {
            $code = sprintf('KK%06d-%d', $id, $n);
            $st->execute([$id, $code, $location, $date, $now, $now, self::uid()]);
        }
    }

    /** Refresh SLiMS's search index row so the OPAC sees the change. */
    public static function reindex(int $id): void
    {
        global $dbs;
        if (!class_exists('simbio_dbop')) require_once SIMBIO . 'simbio_DB/simbio_dbop.inc.php';
        if (!class_exists('biblio_indexer')) require_once MDLBS . 'system/biblio_indexer.inc.php';
        $dbs->query('DELETE FROM search_biblio WHERE biblio_id = ' . (int)$id);
        (new \biblio_indexer($dbs))->makeIndex((int)$id);
    }

    /* ---------- Scan flow ---------- */

    /**
     * The ISBN is always saved, even when no source recognises the book.
     * @throws \InvalidArgumentException on an invalid ISBN.
     */
    public static function createFromIsbn(string $raw, bool $addCopy = false): array
    {
        $isbn = Isbn::normalize($raw);
        if ($isbn === '') throw new \InvalidArgumentException(__('That is not a valid ISBN.'));

        if ($existing = self::findByIsbn($isbn)) {
            if ($addCopy) {
                self::addItems($existing, 1, date('Y-m-d'));
                self::reindex($existing);
            }
            return [
                'status' => 'duplicate', 'id' => $existing, 'isbn' => $isbn,
                'title' => self::title($existing), 'copies' => self::copies($existing),
                'added_copy' => $addCopy, 'cover' => self::thumb($existing),
            ];
        }

        $data = Lookup::find($isbn);
        $found = $data['title'] !== '';
        $id = self::create([
            'title'         => $found ? $data['title'] : 'ISBN ' . $isbn,
            'authors'       => $data['authors'],
            'isbn13'        => $isbn,
            'publisher'     => $data['publisher'],
            'publish_date'  => $data['publish_date'],
            'pages'         => $data['pages'],
            'description'   => $data['description'],
            'needs_details' => !$found,
            'copies'        => 1,
            'cover_url'     => $data['cover_url'],
            'source'        => $data['source'],
        ]);
        if (Settings::get('download_covers')) Covers::fetch($id);
        self::reindex($id);

        return [
            'status'  => $found ? 'created' : 'incomplete',
            'id'      => $id,
            'isbn'    => $isbn,
            'title'   => self::title($id),
            'authors' => implode(', ', $data['authors']),
            'cover'   => self::thumb($id),
        ];
    }

    /** Re-run the lookup; fills blanks only, never overwrites edits. */
    public static function retry(int $id): array
    {
        $st = self::db()->prepare('SELECT * FROM biblio WHERE biblio_id = ?');
        $st->execute([$id]);
        $b = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$b) throw new \InvalidArgumentException(__('Book not found.'));
        $isbn = Isbn::normalize($b['isbn_issn']);
        if ($isbn === '') throw new \InvalidArgumentException(__('This book has no valid ISBN to look up.'));

        $data = Lookup::find($isbn);
        if ($data['title'] === '') return ['status' => 'not_found', 'id' => $id];

        $set = ['opac_hide = 0', 'last_update = ?'];
        $args = [date('Y-m-d H:i:s')];
        if (preg_match('/^ISBN [0-9X]+$/', $b['title'])) { $set[] = 'title = ?'; $args[] = $data['title']; }
        if (trim((string)$b['notes']) === '' && $data['description'] !== '') { $set[] = 'notes = ?'; $args[] = $data['description']; }
        if (empty($b['publisher_id']) && $data['publisher'] !== '') { $set[] = 'publisher_id = ?'; $args[] = self::publisherId($data['publisher']); }
        if (trim((string)$b['publish_year']) === '' && preg_match('/(\d{4})/', $data['publish_date'], $m)) { $set[] = 'publish_year = ?'; $args[] = $m[1]; }
        if (trim((string)$b['collation']) === '' && $data['pages'] !== '') { $set[] = 'collation = ?'; $args[] = (int)$data['pages'] . ' p.'; }

        $hasAuthors = self::db()->prepare('SELECT COUNT(*) FROM biblio_author WHERE biblio_id = ?');
        $hasAuthors->execute([$id]);
        if (!$hasAuthors->fetchColumn() && $data['authors']) {
            self::attachAuthors($id, $data['authors']);
            if (trim((string)$b['sor']) === '') { $set[] = 'sor = ?'; $args[] = mb_substr(implode(', ', $data['authors']), 0, 200); }
        }
        $args[] = $id;
        self::db()->prepare('UPDATE biblio SET ' . implode(', ', $set) . ' WHERE biblio_id = ?')->execute($args);

        $meta = ['needs_details' => 0, 'cover_tried' => null, 'source' => $data['source']];
        if ($data['cover_url'] !== '') $meta['cover_url'] = $data['cover_url'];
        self::setMeta($id, $meta);
        if (empty($b['image']) && Settings::get('download_covers')) Covers::fetch($id);
        self::reindex($id);
        return ['status' => 'updated', 'id' => $id, 'title' => self::title($id)];
    }

    public static function title(int $id): string
    {
        $st = self::db()->prepare('SELECT title FROM biblio WHERE biblio_id = ?');
        $st->execute([$id]);
        return (string)$st->fetchColumn();
    }

    public static function thumb(int $id, int $width = 120): string
    {
        $st = self::db()->prepare('SELECT image FROM biblio WHERE biblio_id = ?');
        $st->execute([$id]);
        $img = (string)$st->fetchColumn();
        return $img === '' ? '' : SWB . 'lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode($img) . '&width=' . $width;
    }
}
