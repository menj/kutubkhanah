<?php
/**
 * Kutubkhanah blog: posts and pages (the WordPress model: drafts, scheduled posts, private posts,
 * trash, sticky posts, revisions, excerpts, featured images and comments).
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

use SLiMS\Kutubkhanah\Settings;

class Posts
{
    const STATUSES = ['draft', 'publish', 'future', 'private', 'trash'];

    public static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    public static function statusLabels(): array
    {
        return ['draft' => __('Draft'), 'publish' => __('Published'), 'future' => __('Scheduled'), 'private' => __('Private'), 'trash' => __('Trash')];
    }

    /** Scheduled posts whose time has come go live (run before any listing). */
    public static function publishDue(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        self::db()->prepare("UPDATE kk_post SET status = 'publish' WHERE status = 'future' AND published_at <= ?")->execute([date('Y-m-d H:i:s')]);
    }

    public static function get(int $id): ?array
    {
        $st = self::db()->prepare('SELECT * FROM kk_post WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** A published post or page by slug ($preview lets a signed-in librarian see drafts). */
    public static function bySlug(string $type, string $slug, bool $preview = false): ?array
    {
        self::publishDue();
        $st = self::db()->prepare('SELECT * FROM kk_post WHERE type = ? AND slug = ?' . ($preview ? " AND status <> 'trash'" : " AND status = 'publish'"));
        $st->execute([$type, $slug]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function url(array $p): string
    {
        return SWB . 'index.php?' . ($p['type'] === 'page' ? 'p=page&slug=' : 'p=blog&post=') . rawurlencode($p['slug']);
    }

    private static function uniqueSlug(string $type, string $slug, int $exceptId): string
    {
        $base = $slug;
        $n = 2;
        $st = self::db()->prepare('SELECT 1 FROM kk_post WHERE type = ? AND slug = ? AND id <> ?');
        while (true) {
            $st->execute([$type, $slug, $exceptId]);
            if (!$st->fetchColumn()) return $slug;
            $slug = substr($base, 0, 190) . '-' . $n++;
        }
    }

    /**
     * Create or update a post. $in: title, content, excerpt, slug, status, published_at (Y-m-d\TH:i),
     * categories (ids), tags (comma list), featured_media, comment_status, sticky, meta_description, menu_order.
     * @throws \InvalidArgumentException
     */
    public static function save(int $id, array $in, array $user, string $type = 'post'): int
    {
        $existing = $id ? self::get($id) : null;
        if ($id && !$existing) throw new \InvalidArgumentException(__('That post was not found.'));
        $type = $existing['type'] ?? (in_array($type, ['post', 'page'], true) ? $type : 'post');

        $title = mb_substr(trim(strip_tags((string)($in['title'] ?? ''))), 0, 300);
        $content = Text::clean((string)($in['content'] ?? ''));
        $status = in_array($in['status'] ?? '', self::STATUSES, true) ? $in['status'] : 'draft';
        if ($title === '' && $status !== 'draft') throw new \InvalidArgumentException(__('Give the post a title before publishing it.'));
        if ($title === '' && trim(Text::plain($content)) === '' && !$existing) throw new \InvalidArgumentException(__('The post is empty.'));

        $when = trim((string)($in['published_at'] ?? ''));
        $date = $when !== '' ? \DateTime::createFromFormat('Y-m-d\TH:i', $when) : false;
        $publishedAt = $date ? $date->format('Y-m-d H:i:00') : ($existing['published_at'] ?? null);
        if (in_array($status, ['publish', 'future'], true)) {
            $publishedAt ??= date('Y-m-d H:i:s');
            $status = $publishedAt > date('Y-m-d H:i:s') ? 'future' : 'publish';
        }

        $slugIn = trim((string)($in['slug'] ?? ''));
        $slug = self::uniqueSlug($type, Text::slug($slugIn !== '' ? $slugIn : ($title !== '' ? $title : 'draft-' . date('YmdHis'))), $id);
        $excerpt = mb_substr(trim(strip_tags((string)($in['excerpt'] ?? ''))), 0, 2000);
        $now = date('Y-m-d H:i:s');
        $vals = [
            'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $content, 'status' => $status,
            'featured_media' => max(0, (int)($in['featured_media'] ?? 0)),
            'published_at' => $publishedAt, 'updated_at' => $now,
            'comment_status' => array_key_exists('comment_status', $in) ? (empty($in['comment_status']) ? 0 : 1) : (int)($existing['comment_status'] ?? 1),
            'sticky' => $type === 'post' && !empty($in['sticky']) ? 1 : 0,
            'meta_description' => mb_substr(trim(strip_tags((string)($in['meta_description'] ?? ''))), 0, 300),
            'menu_order' => (int)($in['menu_order'] ?? 0),
        ];

        $db = self::db();
        if ($existing) {
            // Keep the previous version, like WordPress revisions (last 25 per post).
            if ($existing['content'] !== $content || $existing['title'] !== $title || (string)$existing['excerpt'] !== $excerpt) {
                $db->prepare('INSERT INTO kk_post_revision (post_id, title, excerpt, content, author_name, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                   ->execute([$id, $existing['title'], $existing['excerpt'], $existing['content'], $existing['author_name'], $existing['updated_at']]);
                $old = $db->prepare('SELECT id FROM kk_post_revision WHERE post_id = ? ORDER BY id DESC LIMIT 25, 1000');
                $old->execute([$id]);
                if ($ids = $old->fetchAll(\PDO::FETCH_COLUMN)) $db->exec('DELETE FROM kk_post_revision WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
            }
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($vals)));
            $db->prepare("UPDATE kk_post SET $set WHERE id = ?")->execute(array_merge(array_values($vals), [$id]));
        } else {
            $vals += ['type' => $type, 'author_id' => (int)($user['id'] ?? 0), 'author_name' => mb_substr((string)($user['name'] ?? ''), 0, 100), 'created_at' => $now];
            $cols = array_keys($vals);
            $db->prepare('INSERT INTO kk_post (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
               ->execute(array_values($vals));
            $id = (int)$db->lastInsertId();
        }

        if ($type === 'post') {
            $cats = array_values(array_filter(array_map('intval', (array)($in['categories'] ?? []))));
            if (!$cats) $cats = [Terms::defaultCategory()];
            Terms::setForPost($id, 'category', $cats);
            Terms::setForPost($id, 'tag', Terms::idsFromNames('tag', (string)($in['tags'] ?? '')));
        }
        return $id;
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) return false;
        $p = self::get($id);
        if (!$p) return false;
        if ($status === 'publish') {
            $at = $p['published_at'] ?: date('Y-m-d H:i:s');
            if ($at > date('Y-m-d H:i:s')) $status = 'future';
            self::db()->prepare('UPDATE kk_post SET status = ?, published_at = ? WHERE id = ?')->execute([$status, $at, $id]);
        } else {
            self::db()->prepare('UPDATE kk_post SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
        return true;
    }

    /** Delete for good (from the trash): the post, its terms, comments and revisions. */
    public static function delete(int $id): bool
    {
        $db = self::db();
        foreach (['kk_post_term' => 'post_id', 'kk_comment' => 'post_id', 'kk_post_revision' => 'post_id'] as $t => $c) {
            $db->prepare("DELETE FROM $t WHERE $c = ?")->execute([$id]);
        }
        $st = $db->prepare('DELETE FROM kk_post WHERE id = ?');
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /**
     * Listing. $f: type, status ('' = all but trash; 'public' = published), term (id), search, month (YYYY-MM), page, per_page.
     * @return array{rows: array, total: int}
     */
    public static function query(array $f): array
    {
        self::publishDue();
        $where = ['p.type = ?'];
        $args = [$f['type'] ?? 'post'];
        $status = (string)($f['status'] ?? '');
        if ($status === 'public') $where[] = "p.status = 'publish'";
        elseif ($status === '') $where[] = "p.status <> 'trash'";
        else { $where[] = 'p.status = ?'; $args[] = $status; }
        if (!empty($f['term'])) { $where[] = 'EXISTS (SELECT 1 FROM kk_post_term pt WHERE pt.post_id = p.id AND pt.term_id = ?)'; $args[] = (int)$f['term']; }
        if (!empty($f['month']) && preg_match('/^\d{4}-\d{2}$/', $f['month'])) { $where[] = "DATE_FORMAT(p.published_at, '%Y-%m') = ?"; $args[] = $f['month']; }
        if (($q = trim((string)($f['search'] ?? ''))) !== '') {
            $where[] = '(p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $count = self::db()->prepare("SELECT COUNT(*) FROM kk_post p WHERE $w");
        $count->execute($args);
        $per = max(1, min(100, (int)($f['per_page'] ?? 10)));
        $page = max(1, (int)($f['page'] ?? 1));
        $order = ($f['type'] ?? 'post') === 'page' ? 'p.menu_order, p.title'
            : (!empty($f['sticky_first']) ? 'p.sticky DESC, ' : '') . 'COALESCE(p.published_at, p.updated_at) DESC, p.id DESC';
        $st = self::db()->prepare("SELECT p.* FROM kk_post p WHERE $w ORDER BY $order LIMIT " . (($page - 1) * $per) . ", $per");
        $st->execute($args);
        return ['rows' => $st->fetchAll(\PDO::FETCH_ASSOC), 'total' => (int)$count->fetchColumn()];
    }

    public static function counts(string $type = 'post'): array
    {
        self::publishDue();
        $st = self::db()->prepare('SELECT status, COUNT(*) FROM kk_post WHERE type = ? GROUP BY status');
        $st->execute([$type]);
        return $st->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Previous and next published posts. */
    public static function adjacent(array $p): array
    {
        $out = [];
        foreach (['prev' => ['<', 'DESC'], 'next' => ['>', 'ASC']] as $k => [$op, $dir]) {
            $st = self::db()->prepare("SELECT id, title, slug, type FROM kk_post WHERE type = 'post' AND status = 'publish'
                AND (published_at $op ? OR (published_at = ? AND id $op ?)) ORDER BY published_at $dir, id $dir LIMIT 1");
            $st->execute([$p['published_at'], $p['published_at'], $p['id']]);
            $out[$k] = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        return $out;
    }

    /** Months with published posts, newest first. */
    public static function archives(): array
    {
        self::publishDue();
        return self::db()->query("SELECT DATE_FORMAT(published_at, '%Y-%m') AS ym, COUNT(*) AS n FROM kk_post
            WHERE type = 'post' AND status = 'publish' GROUP BY ym ORDER BY ym DESC LIMIT 36")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function revisions(int $id): array
    {
        $st = self::db()->prepare('SELECT id, title, author_name, created_at FROM kk_post_revision WHERE post_id = ? ORDER BY id DESC');
        $st->execute([$id]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Put a revision back (the current text becomes a revision itself). */
    public static function restoreRevision(int $revisionId, array $user): int
    {
        $st = self::db()->prepare('SELECT * FROM kk_post_revision WHERE id = ?');
        $st->execute([$revisionId]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$r || !($p = self::get((int)$r['post_id']))) throw new \InvalidArgumentException(__('That revision was not found.'));
        $terms = Terms::forPost((int)$p['id']);
        return self::save((int)$p['id'], [
            'title' => $r['title'], 'content' => $r['content'], 'excerpt' => $r['excerpt'], 'slug' => $p['slug'], 'status' => $p['status'],
            'published_at' => $p['published_at'] ? date('Y-m-d\TH:i', strtotime($p['published_at'])) : '', 'featured_media' => $p['featured_media'],
            'comment_status' => $p['comment_status'], 'sticky' => $p['sticky'], 'meta_description' => $p['meta_description'], 'menu_order' => $p['menu_order'],
            'categories' => array_column($terms['category'] ?? [], 'id'), 'tags' => implode(', ', array_column($terms['tag'] ?? [], 'name')),
        ], $user);
    }

    public static function enabled(): bool
    {
        return (bool)Settings::get('blog_enable');
    }
}
