<?php
/**
 * Kutubkhanah: public read-only JSON API (ported from the Private Library REST endpoints).
 * Only books visible in the OPAC are exposed. Writes are done in the admin.
 *
 *   index.php?p=kk-api&r=books[&q=&genre=&format=&lang=&page=1&per_page=20]
 *   index.php?p=kk-api&r=book&id=123
 *   index.php?p=kk-api&r=stats
 *   index.php?p=kk-api&r=authors&q=name
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Api
{
    public static function respond(array $get): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');
        try {
            [$code, $data, $headers] = self::route($get);
        } catch (\Throwable $e) {
            error_log('Kutubkhanah API: ' . $e->getMessage());
            [$code, $data, $headers] = [500, ['error' => 'server_error'], []];
        }
        http_response_code($code);
        foreach ($headers as $k => $v) header("$k: $v");
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function route(array $g): array
    {
        switch ($g['r'] ?? 'books') {
            case 'books':  return self::books($g);
            case 'book':   return self::book((int)($g['id'] ?? 0));
            case 'stats':  return [200, Library::stats(), []];
            case 'authors': return self::authors((string)($g['q'] ?? ''));
        }
        return [404, ['error' => 'unknown_route'], []];
    }

    private static function format(array $b): array
    {
        $base = rtrim((string)SWB, '/') . '/';
        return [
            'id'             => (int)$b['biblio_id'],
            'title'          => $b['title'],
            'authors'        => array_values(array_filter(array_map('trim', explode(',', (string)$b['authors'])))),
            'isbn'           => $b['isbn_issn'] ?: null,
            'publisher'      => $b['publisher_name'] ?: null,
            'year'           => $b['publish_year'] ?: null,
            'language'       => $b['language_name'] ?: null,
            'call_number'    => $b['call_number'] ?: null,
            'format'         => $b['format'] ?: null,
            'reading_status' => Settings::get('show_reading') ? (Settings::readingStatuses()[$b['reading_status']] ?? null) : null,
            'genres'         => array_values(array_filter(array_map('trim', explode('|', (string)$b['genres'])))),
            'tags'           => array_values(array_filter(array_map('trim', explode(',', (string)$b['tags'])))),
            'cover_url'      => $b['image'] ? $base . 'images/docs/' . rawurlencode($b['image']) : null,
            'url'            => Permalink::book((int)$b['biblio_id']),
        ];
    }

    private static function select(): string
    {
        return "SELECT b.biblio_id, b.title, b.isbn_issn, b.publish_year, b.call_number, b.image,
                       p.publisher_name, lg.language_name, COALESCE(k.format, '') AS format,
                       COALESCE(k.reading_status, '') AS reading_status, COALESCE(k.tags, '') AS tags,
                       (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level SEPARATOR ', ') FROM biblio_author ba
                          JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) AS authors,
                       (SELECT GROUP_CONCAT(t.topic SEPARATOR '|') FROM biblio_topic bt
                          JOIN mst_topic t ON t.topic_id = bt.topic_id WHERE bt.biblio_id = b.biblio_id) AS genres
                FROM biblio b
                LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
                LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id
                LEFT JOIN mst_language lg ON lg.language_id = b.language_id
                WHERE " . Library::VISIBLE;
    }

    private static function books(array $g): array
    {
        $per = max(1, min(100, (int)($g['per_page'] ?? 20)));
        $page = max(1, (int)($g['page'] ?? 1));
        $where = '';
        $args = [];
        if (($q = trim((string)($g['q'] ?? ''))) !== '') {
            $where .= " AND (b.title LIKE ? OR b.isbn_issn LIKE ? OR EXISTS (SELECT 1 FROM biblio_author x JOIN mst_author y ON y.author_id = x.author_id
                         WHERE x.biblio_id = b.biblio_id AND y.author_name LIKE ?))";
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, $like);
        }
        if (($genre = (int)($g['genre'] ?? 0)) > 0) {
            $where .= ' AND EXISTS (SELECT 1 FROM biblio_topic z WHERE z.biblio_id = b.biblio_id AND z.topic_id = ?)';
            $args[] = $genre;
        }
        if (in_array($g['format'] ?? '', Settings::formats(), true)) {
            $where .= ' AND k.format = ?';
            $args[] = $g['format'];
        }
        if (($lang = (string)($g['lang'] ?? '')) !== '') {
            $where .= ' AND b.language_id = ?';
            $args[] = $lang;
        }
        $db = DB::getInstance();
        $count = $db->prepare('SELECT COUNT(*) FROM (' . self::select() . $where . ') t');
        $count->execute($args);
        $total = (int)$count->fetchColumn();
        $st = $db->prepare(self::select() . $where . ' ORDER BY b.title LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
        $st->execute($args);
        $items = array_map([self::class, 'format'], $st->fetchAll(\PDO::FETCH_ASSOC));
        return [200, $items, ['X-Total-Count' => $total, 'X-Total-Pages' => (int)ceil($total / $per)]];
    }

    private static function book(int $id): array
    {
        $st = DB::getInstance()->prepare(self::select() . ' AND b.biblio_id = ?');
        $st->execute([$id]);
        $b = $st->fetch(\PDO::FETCH_ASSOC);
        return $b ? [200, self::format($b), []] : [404, ['error' => 'not_found'], []];
    }

    private static function authors(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [400, ['error' => 'query_too_short'], []];
        $st = DB::getInstance()->prepare(
            "SELECT a.author_id, a.author_name, COALESCE(m.nationality, '') AS nationality
             FROM mst_author a LEFT JOIN kk_author m ON m.author_id = a.author_id
             WHERE a.author_name LIKE ? AND EXISTS (SELECT 1 FROM biblio_author x JOIN biblio b ON b.biblio_id = x.biblio_id
                   LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id WHERE x.author_id = a.author_id AND " . Library::VISIBLE . ")
             ORDER BY a.author_name LIMIT 10"
        );
        $st->execute(['%' . addcslashes($q, '%_\\') . '%']);
        $out = array_map(fn($r) => [
            'id' => (int)$r['author_id'], 'name' => $r['author_name'], 'nationality' => $r['nationality'] ?: null,
            'url' => rtrim((string)SWB, '/') . '/index.php?p=author&id=' . (int)$r['author_id'],
        ], $st->fetchAll(\PDO::FETCH_ASSOC));
        return [200, $out, []];
    }
}
