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

    /** Look one author up. Returns true when a portrait or biography was found. */
    public static function fetch(int $id): bool
    {
        $rows = Library::rows('SELECT author_name FROM mst_author WHERE author_id = ?', [$id]);
        if (!$rows) return false;
        $name = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', (string)$rows[0]['author_name']));
        // "Surname, Given" becomes "Given Surname" for the search.
        if (preg_match('/^([^,]+),\s*(.+)$/', $name, $m)) $name = trim($m[2] . ' ' . $m[1]);
        $photo = ''; $bio = ''; $source = '';
        $d = Http::json('https://openlibrary.org/search/authors.json?q=' . rawurlencode($name) . '&limit=1');
        $key = $d['docs'][0]['key'] ?? '';
        if (is_string($key) && preg_match('/^OL\d+A$/', $key)) {
            $a = Http::json('https://openlibrary.org/authors/' . $key . '.json');
            $b = $a['bio'] ?? '';
            $bio = is_array($b) ? (string)($b['value'] ?? '') : (string)$b;
            $bio = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/\(\[source\].*?\)|\[\d+\]:.*$/s', '', $bio))));
            $photo = Covers::download('https://covers.openlibrary.org/a/olid/' . $key . '-L.jpg?default=false', 'kk-author-' . $id);
            $source = ($bio !== '' || $photo !== '') ? 'Open Library' : '';
        }
        $db = DB::getInstance();
        $now = date('Y-m-d');
        if (Library::rows('SELECT 1 AS x FROM kk_author WHERE author_id = ?', [$id])) {
            $db->prepare('UPDATE kk_author SET photo = ?, bio = ?, bio_source = ?, photo_tried = ? WHERE author_id = ? AND photo = \'\'')
               ->execute([$photo, mb_substr($bio, 0, 4000), $source, $now, $id]);
            $db->prepare('UPDATE kk_author SET photo_tried = ? WHERE author_id = ?')->execute([$now, $id]);
        } else {
            $db->prepare('INSERT INTO kk_author (author_id, photo, bio, bio_source, photo_tried) VALUES (?, ?, ?, ?, ?)')
               ->execute([$id, $photo, mb_substr($bio, 0, 4000), $source, $now]);
        }
        return $photo !== '' || $bio !== '';
    }

    public static function photoUrl(string $file, int $w = 300): string
    {
        return $file === '' ? '' : Library::cover($file, $w);
    }
}
