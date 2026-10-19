<?php
/**
 * Kutubkhanah: cover download into images/docs/ (SLiMS's cover folder).
 * Tries the stored URL, Open Library by ISBN, then Google Books.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Covers
{
    /** @return bool true when a cover was saved. */
    public static function fetch(int $id): bool
    {
        $db = DB::getInstance();
        $st = $db->prepare('SELECT isbn_issn FROM biblio WHERE biblio_id = ?');
        $st->execute([$id]);
        $isbn13 = Isbn::normalize((string)$st->fetchColumn());
        $meta = Books::meta($id);

        $urls = [];
        if (!empty($meta['cover_url'])) $urls[] = $meta['cover_url'];
        if ($isbn13 !== '') {
            $urls[] = 'https://covers.openlibrary.org/b/isbn/' . $isbn13 . '-L.jpg?default=false';
            if (($i10 = Isbn::to10($isbn13)) !== '') $urls[] = 'https://covers.openlibrary.org/b/isbn/' . $i10 . '-L.jpg?default=false';
        }

        $ok = false;
        foreach (array_unique($urls) as $url) {
            if (self::save($url, $id)) { $ok = true; break; }
        }
        if (!$ok && $isbn13 !== '') {
            $g = Lookup::googleByIsbn($isbn13);
            if ($g && $g['cover_url'] !== '') {
                Books::setMeta($id, ['cover_url' => $g['cover_url']]);
                $ok = self::save($g['cover_url'], $id);
            }
        }
        Books::setMeta($id, ['cover_tried' => date('Y-m-d H:i:s')]);
        return $ok;
    }

    private static function save(string $url, int $id): bool
    {
        if (!preg_match('#^https?://#i', $url)) return false;
        [$code, $body] = Http::get($url, 20);
        if ($code !== 200 || $body === '') return false;
        $info = @getimagesizefromstring($body);
        // Reject placeholders and tracking pixels.
        if (!$info || $info[0] < 80 || $info[1] < 80) return false;
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null;
        if (!$ext) return false;

        $file = 'kk-cover-' . $id . '.' . $ext;
        $dir = IMGBS . 'docs' . DS;
        if (!is_dir($dir) || file_put_contents($dir . $file, $body) === false) return false;
        DB::getInstance()->prepare('UPDATE biblio SET image = ?, last_update = ? WHERE biblio_id = ?')
            ->execute([$file, date('Y-m-d H:i:s'), $id]);
        return true;
    }

    private static function pendingSql(): string
    {
        return "FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
                WHERE (b.image IS NULL OR b.image = '') AND k.cover_tried IS NULL
                AND b.isbn_issn IS NOT NULL AND b.isbn_issn <> ''";
    }

    public static function pending(int $limit): array
    {
        return DB::getInstance()->query('SELECT b.biblio_id ' . self::pendingSql() . ' ORDER BY b.biblio_id LIMIT ' . (int)$limit)
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function countPending(): int
    {
        return (int)DB::getInstance()->query('SELECT COUNT(*) ' . self::pendingSql())->fetchColumn();
    }

    public static function resetTried(): void
    {
        DB::getInstance()->exec(
            "UPDATE kk_book k JOIN biblio b ON b.biblio_id = k.biblio_id
             SET k.cover_tried = NULL WHERE b.image IS NULL OR b.image = ''"
        );
    }
}
