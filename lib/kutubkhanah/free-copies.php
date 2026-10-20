<?php
/**
 * Kutubkhanah: finds a free-to-read copy of a book you own (Project Gutenberg, HathiTrust full view, Internet Archive)
 * and saves the link in "Digital copy". Only books with an empty link are touched.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class FreeCopies
{
    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s*[:(\[].*$/u', '', $s)));
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));
    }

    /** Gutenberg: the first result whose title matches ours and whose author shares a name part. */
    private static function gutenberg(string $title, array $authors): string
    {
        $t = self::norm($title);
        if ($t === '') return '';
        $last = $authors ? preg_split('/[\s,]+/u', trim($authors[0]))[0] ?? '' : '';
        $d = Http::json('https://gutendex.com/books/?search=' . rawurlencode($title . ' ' . $last));
        foreach ($d['results'] ?? [] as $r) {
            if (self::norm((string)($r['title'] ?? '')) !== $t || empty($r['id'])) continue;
            $names = mb_strtolower(implode(' ', array_map(fn($a) => (string)($a['name'] ?? ''), $r['authors'] ?? [])));
            $parts = array_filter(preg_split('/[\s,]+/u', mb_strtolower(implode(' ', $authors))), fn($p) => mb_strlen($p) > 2);
            $ok = !$authors;
            foreach ($parts as $p) if (str_contains($names, $p)) { $ok = true; break; }
            if ($ok) return 'https://www.gutenberg.org/ebooks/' . (int)$r['id'];
        }
        return '';
    }

    /** Returns true when a link was saved. */
    public static function book(int $id): bool
    {
        $b = Library::rows('SELECT title, isbn_issn FROM biblio WHERE biblio_id = ?', [$id])[0] ?? null;
        if (!$b) return false;
        $m = Books::meta($id);
        $db = DB::getInstance();
        if (!Library::rows('SELECT 1 AS x FROM kk_book WHERE biblio_id = ?', [$id])) {
            $db->prepare('INSERT INTO kk_book (biblio_id) VALUES (?)')->execute([$id]);
        }
        $link = '';
        if (trim((string)($m['digital_link'] ?? '')) === '') {
            $authors = array_column(Library::rows('SELECT a.author_name FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = ? ORDER BY ba.level', [$id]), 'author_name');
            $link = self::gutenberg((string)$b['title'], $authors);
            $isbn = preg_replace('/[^0-9Xx]/', '', (string)$b['isbn_issn']);
            if ($link === '' && $isbn !== '') {
                $r = Lookup::hathiTrust(Http::get('https://catalog.hathitrust.org/api/volumes/brief/isbn/' . rawurlencode($isbn) . '.json'));
                $link = (string)($r['digital_link'] ?? '');
                if ($link === '') $link = (string)(Lookup::internetArchive(Http::get(Lookup::requests($isbn)['internetarchive'] ?? 'https://archive.org/advancedsearch.php?output=json&rows=1&q=' . rawurlencode('isbn:' . $isbn))) ['digital_link'] ?? '');
            }
        }
        if ($link !== '') $db->prepare('UPDATE kk_book SET digital_link = ?, fc_tried = ? WHERE biblio_id = ?')->execute([$link, date('Y-m-d'), $id]);
        else $db->prepare('UPDATE kk_book SET fc_tried = ? WHERE biblio_id = ?')->execute([date('Y-m-d'), $id]);
        return $link !== '';
    }

    private const PENDING = "FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE COALESCE(k.fc_tried, '') = '' AND COALESCE(k.digital_link, '') = ''";

    public static function pending(int $limit = 3): array
    {
        return Library::rows('SELECT b.biblio_id ' . self::PENDING . ' ORDER BY b.biblio_id LIMIT ' . max(1, $limit));
    }

    public static function countPending(): int
    {
        return (int)(Library::rows('SELECT COUNT(*) AS n ' . self::PENDING)[0]['n'] ?? 0);
    }

    public static function resetTried(): void
    {
        DB::getInstance()->query("UPDATE kk_book SET fc_tried = '' WHERE COALESCE(digital_link, '') = ''");
    }
}
