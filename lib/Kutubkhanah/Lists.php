<?php
/**
 * Kutubkhanah: reading lists (after PMB). Curated, ordered lists of books such as "Start here" or
 * "Summer 2026", each with its own public page.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Lists
{
    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    public static function all(bool $publicOnly = false): array
    {
        $rows = self::db()->query(
            'SELECT l.*, (SELECT COUNT(*) FROM kk_list_item i JOIN biblio b ON b.biblio_id = i.biblio_id WHERE i.list_id = l.id'
            . ($publicOnly ? ' AND b.opac_hide = 0' : '') . ') AS n
             FROM kk_list l ' . ($publicOnly ? 'WHERE l.is_public = 1 ' : '') . 'ORDER BY l.title'
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    public static function get(int $id): ?array
    {
        $st = self::db()->prepare('SELECT * FROM kk_list WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function bySlug(string $slug): ?array
    {
        $st = self::db()->prepare('SELECT * FROM kk_list WHERE slug = ? AND is_public = 1');
        $st->execute([$slug]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function slug(string $title, int $exceptId = 0): string
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($title)), '-') ?: 'list';
        $base = mb_substr($base, 0, 80);
        $slug = $base;
        for ($i = 2; $i < 100; $i++) {
            $st = self::db()->prepare('SELECT id FROM kk_list WHERE slug = ? AND id <> ?');
            $st->execute([$slug, $exceptId]);
            if (!$st->fetchColumn()) return $slug;
            $slug = $base . '-' . $i;
        }
        return $base . '-' . bin2hex(random_bytes(2));
    }

    /** Create or update; returns the list id. @throws \InvalidArgumentException */
    public static function save(int $id, array $v): int
    {
        $title = mb_substr(trim(strip_tags((string)($v['title'] ?? ''))), 0, 200);
        if ($title === '') throw new \InvalidArgumentException(__('Give the list a title.'));
        $desc = mb_substr(trim(strip_tags((string)($v['description'] ?? ''))), 0, 5000);
        $pub = empty($v['is_public']) ? 0 : 1;
        if ($id > 0 && self::get($id)) {
            self::db()->prepare('UPDATE kk_list SET title = ?, slug = ?, description = ?, is_public = ? WHERE id = ?')
                ->execute([$title, self::slug($title, $id), $desc, $pub, $id]);
            return $id;
        }
        $slug = self::slug($title);
        // Read lastInsertId from the same handle that ran the INSERT.
        $db = self::db();
        $db->prepare('INSERT INTO kk_list (title, slug, description, is_public, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$title, $slug, $desc, $pub, date('Y-m-d H:i:s')]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        self::db()->prepare('DELETE FROM kk_list_item WHERE list_id = ?')->execute([$id]);
        self::db()->prepare('DELETE FROM kk_list WHERE id = ?')->execute([$id]);
    }

    /** Books of a list in order. */
    public static function items(int $id, bool $publicOnly = false): array
    {
        $st = self::db()->prepare(
            'SELECT b.biblio_id, b.title, b.publish_year, b.image, i.note, i.pos
             FROM kk_list_item i JOIN biblio b ON b.biblio_id = i.biblio_id WHERE i.list_id = ?'
            . ($publicOnly ? ' AND b.opac_hide = 0' : '') . ' ORDER BY i.pos, b.title'
        );
        $st->execute([$id]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function add(int $list, int $biblio, string $note = ''): void
    {
        if (!self::get($list) || Books::title($biblio) === '') throw new \InvalidArgumentException(__('That book or list no longer exists.'));
        $st = self::db()->prepare('SELECT 1 FROM kk_list_item WHERE list_id = ? AND biblio_id = ?');
        $st->execute([$list, $biblio]);
        if ($st->fetchColumn()) return;
        $st = self::db()->prepare('SELECT COALESCE(MAX(pos), 0) + 1 FROM kk_list_item WHERE list_id = ?');
        $st->execute([$list]);
        self::db()->prepare('INSERT INTO kk_list_item (list_id, biblio_id, pos, note) VALUES (?, ?, ?, ?)')
            ->execute([$list, $biblio, (int)$st->fetchColumn(), mb_substr(trim(strip_tags($note)), 0, 500)]);
    }

    public static function remove(int $list, int $biblio): void
    {
        self::db()->prepare('DELETE FROM kk_list_item WHERE list_id = ? AND biblio_id = ?')->execute([$list, $biblio]);
    }

    public static function note(int $list, int $biblio, string $note): void
    {
        self::db()->prepare('UPDATE kk_list_item SET note = ? WHERE list_id = ? AND biblio_id = ?')
            ->execute([mb_substr(trim(strip_tags($note)), 0, 500), $list, $biblio]);
    }

    /** Move one book up (-1) or down (1) and renumber the list. */
    public static function move(int $list, int $biblio, int $dir): void
    {
        $ids = array_map(fn($r) => (int)$r['biblio_id'], self::items($list));
        $i = array_search($biblio, $ids, true);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) return;
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $st = self::db()->prepare('UPDATE kk_list_item SET pos = ? WHERE list_id = ? AND biblio_id = ?');
        foreach ($ids as $n => $id) $st->execute([$n + 1, $list, $id]);
    }

    /** Lists a book is on (public ones), for its detail page. */
    public static function containing(int $biblio): array
    {
        $st = self::db()->prepare('SELECT l.title, l.slug FROM kk_list l JOIN kk_list_item i ON i.list_id = l.id
            WHERE i.biblio_id = ? AND l.is_public = 1 ORDER BY l.title');
        $st->execute([$biblio]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function url(string $slug): string
    {
        return 'index.php?p=lists&amp;list=' . rawurlencode($slug);
    }

    /* ---------- Public pages ---------- */

    public static function index(): string
    {
        $e = [Library::class, 'e'];
        $rows = array_filter(self::all(true), fn($r) => (int)$r['n'] > 0);
        if (!$rows) return '<p class="kk-muted">' . __('No reading lists yet.') . '</p>';
        $out = '<ul class="kk-series-list">';
        foreach ($rows as $r) {
            $out .= '<li><a href="' . self::url($r['slug']) . '"><span class="kk-series-list__name" dir="auto">' . $e($r['title']) . '</span>'
                . '<span class="kk-series-list__count">' . $e(sprintf(__('%d books'), (int)$r['n'])) . '</span></a></li>';
        }
        return $out . '</ul>';
    }

    public static function page(array $list): string
    {
        $e = [Library::class, 'e'];
        $items = self::items((int)$list['id'], true);
        $notes = [];
        foreach ($items as $it) $notes[(int)$it['biblio_id']] = (string)$it['note'];
        $html = '<div class="kk-series">';
        if ($list['description'] !== '') $html .= '<p class="kk-series__desc" dir="auto">' . nl2br($e($list['description'])) . '</p>';
        $html .= '<p class="kk-series__count">' . $e(sprintf(__('%d books'), count($items))) . '</p></div>';
        if (!$items) return $html;
        $ids = array_map(fn($b) => (int)$b['biblio_id'], $items);
        $cards = Library::books('b.biblio_id IN (' . implode(',', $ids) . ')', [], 'b.title', count($ids));
        $byId = [];
        foreach ($cards as $c) $byId[(int)$c['biblio_id']] = $c;
        $grid = '';
        foreach ($ids as $id) {
            if (!isset($byId[$id])) continue;
            $grid .= Library::card($byId[$id]);
            if ($notes[$id] !== '') $grid = substr($grid, 0, -strlen('</div></article>')) . '<p class="kk-book__note" dir="auto">' . $e($notes[$id]) . '</p></div></article>';
        }
        return $html . '<div class="kk-grid">' . $grid . '</div>';
    }
}
