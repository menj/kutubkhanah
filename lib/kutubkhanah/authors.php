<?php
/**
 * Kutubkhanah: author portraits and short biographies from Open Library, saved on this server.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Authors
{
    /** Authors with books that have not been looked up yet. */
    public static function pending(int $limit = 8): array
    {
        return Library::rows(
            "SELECT a.author_id, a.author_name FROM mst_author a
             LEFT JOIN kk_author m ON m.author_id = a.author_id
             WHERE COALESCE(m.photo_tried, '') = '' AND EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.author_id = a.author_id)
             ORDER BY a.author_id LIMIT " . max(1, $limit)
        );
    }

    public static function countPending(): int
    {
        return (int)(Library::rows(
            "SELECT COUNT(*) AS n FROM mst_author a LEFT JOIN kk_author m ON m.author_id = a.author_id
             WHERE COALESCE(m.photo_tried, '') = '' AND EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.author_id = a.author_id)"
        )[0]['n'] ?? 0);
    }

    public static function resetTried(): void
    {
        DB::getInstance()->query("UPDATE kk_author SET photo_tried = '' WHERE photo = ''");
    }

    /** The saved row for one author (empty values when none). */
    public static function meta(int $id): array
    {
        return Library::rows('SELECT * FROM kk_author WHERE author_id = ?', [$id])[0] ?? [];
    }

    /** Make sure a kk_author row exists. */
    private static function ensure(int $id): void
    {
        if (!Library::rows('SELECT 1 AS x FROM kk_author WHERE author_id = ?', [$id])) {
            DB::getInstance()->prepare('INSERT INTO kk_author (author_id) VALUES (?)')->execute([$id]);
        }
    }

    /** Look one author up. Returns true when a portrait or biography was found. Rejected authors are skipped. */
    public static function fetch(int $id): bool
    {
        $rows = Library::rows('SELECT author_name FROM mst_author WHERE author_id = ?', [$id]);
        if (!$rows) return false;
        $m = self::meta($id);
        $db = DB::getInstance();
        $now = date('Y-m-d');
        if (($m['reviewed'] ?? '') === 'r') {
            self::ensure($id);
            $db->prepare('UPDATE kk_author SET photo_tried = ? WHERE author_id = ?')->execute([$now, $id]);
            return false;
        }
        // A name typed by the owner wins; otherwise "Surname, Given" becomes "Given Surname".
        $name = trim((string)($m['search_name'] ?? ''));
        if ($name === '') {
            $name = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', (string)$rows[0]['author_name']));
            if (!preg_match('/[\x{0600}-\x{06FF}\x{3400}-\x{9FFF}]/u', $name) && preg_match('/^([^,]+),\s*(.+)$/', $name, $mm)) $name = trim($mm[2] . ' ' . $mm[1]);
        }
        $photo = ''; $bio = ''; $source = ''; $olName = '';
        $key = trim((string)($m['ol_key'] ?? ''));
        if (!preg_match('/^OL\d+A$/', $key)) {
            $key = '';
            $d = Http::json('https://openlibrary.org/search/authors.json?q=' . rawurlencode($name) . '&limit=1');
            $key = $d['docs'][0]['key'] ?? '';
            $olName = (string)($d['docs'][0]['name'] ?? '');
            if ($key === '' && ($nat = trim((string)($m['native_name'] ?? ''))) !== '') {
                $d = Http::json('https://openlibrary.org/search/authors.json?q=' . rawurlencode($nat) . '&limit=1');
                $key = $d['docs'][0]['key'] ?? '';
                $olName = (string)($d['docs'][0]['name'] ?? '');
            }
        }
        if (is_string($key) && preg_match('/^OL\d+A$/', $key)) {
            $a = Http::json('https://openlibrary.org/authors/' . $key . '.json');
            if ($olName === '') $olName = (string)($a['name'] ?? '');
            $b = $a['bio'] ?? '';
            $bio = is_array($b) ? (string)($b['value'] ?? '') : (string)$b;
            $bio = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/\(\[source\].*?\)|\[\d+\]:.*$/s', '', $bio))));
            $photo = Covers::download('https://covers.openlibrary.org/a/olid/' . $key . '-L.jpg?default=false', 'kk-author-' . $id);
            $source = ($bio !== '' || $photo !== '') ? 'Open Library' : '';
        }
        // Keep anything already saved (for example from Wikidata) when Open Library has nothing.
        if ($photo === '') $photo = (string)($m['photo'] ?? '');
        if ($bio === '') { $bio = (string)($m['bio'] ?? ''); if ($source === '') $source = (string)($m['bio_source'] ?? ''); }
        self::ensure($id);
        $db->prepare('UPDATE kk_author SET photo = ?, bio = ?, bio_source = ?, ol_key = ?, ol_name = ?, reviewed = ?, photo_tried = ? WHERE author_id = ?')
           ->execute([$photo, mb_substr($bio, 0, 4000), $source, $key, $source !== '' ? $olName : '', '', $now, $id]);
        return $photo !== '' || $bio !== '';
    }

    /** Owner review: save overrides, approve a match, reject it, or look the author up again. */
    public static function review(int $id, string $op, array $in = []): bool
    {
        if (!Library::rows('SELECT 1 AS x FROM mst_author WHERE author_id = ?', [$id])) return false;
        self::ensure($id);
        $db = DB::getInstance();
        $key = strtoupper(trim((string)($in['ol_key'] ?? '')));
        if ($key !== '' && !preg_match('/^OL\d+A$/', $key)) $key = '';
        if (in_array($op, ['save', 'refetch', 'wikidata'], true)) {
            $db->prepare('UPDATE kk_author SET search_name = ?, sort_name = ?, native_name = ?, ol_key = ?, isni = ?, wd_id = ? WHERE author_id = ?')
               ->execute([mb_substr(trim((string)($in['search_name'] ?? '')), 0, 200), mb_substr(trim((string)($in['sort_name'] ?? '')), 0, 200), mb_substr(trim((string)($in['native_name'] ?? '')), 0, 200), $key,
                           mb_substr(trim((string)($in['isni'] ?? '')), 0, 30), preg_match('/^Q\d+$/i', trim((string)($in['wd_id'] ?? ''))) ? strtoupper(trim($in['wd_id'])) : '', $id]);
        }
        if ($op === 'approve') {
            $db->prepare("UPDATE kk_author SET reviewed = 'a' WHERE author_id = ?")->execute([$id]);
        } elseif ($op === 'reject') {
            $db->prepare("UPDATE kk_author SET photo = '', bio = '', bio_source = '', ol_key = '', ol_name = '', wd_id = '', wd_label = '', reviewed = 'r', photo_tried = ? WHERE author_id = ?")
               ->execute([date('Y-m-d'), $id]);
        } elseif ($op === 'wikidata') {
            Wikidata::author($id);
            Viaf::author($id);
        } elseif ($op === 'refetch') {
            $db->prepare("UPDATE kk_author SET reviewed = '' WHERE author_id = ?")->execute([$id]);
            self::fetch($id);
        }
        return true;
    }

    /** Authors for the review table: unreviewed matches first, optionally filtered by name. */
    public static function reviewList(string $q = '', int $limit = 25): array
    {
        $w = "EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.author_id = a.author_id)"; $args = [];
        if ($q !== '') { $w .= ' AND a.author_name LIKE ?'; $args[] = '%' . $q . '%'; }
        else $w .= " AND (COALESCE(m.bio_source, '') <> '' OR COALESCE(m.wd_id, '') <> '') AND COALESCE(m.reviewed, '') = ''";
        return Library::rows(
            "SELECT a.author_id, a.author_name, m.ol_key, m.ol_name, m.wd_id, m.wd_label, m.viaf_id, m.isni, m.search_name, m.sort_name, m.native_name, m.reviewed, m.bio_source
             FROM mst_author a LEFT JOIN kk_author m ON m.author_id = a.author_id WHERE $w ORDER BY a.author_name LIMIT " . max(1, $limit),
            $args
        );
    }

    public static function photoUrl(string $file, int $w = 300): string
    {
        return $file === '' ? '' : Library::cover($file, $w);
    }
}
