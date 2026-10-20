<?php
/**
 * Kutubkhanah blog: categories (nested) and tags.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

class Terms
{
    private static function db(): \PDO
    {
        return Posts::db();
    }

    /** All terms of a taxonomy with their published-post counts. */
    public static function all(string $tax, bool $withCounts = true): array
    {
        $sql = $withCounts
            ? "SELECT t.*, (SELECT COUNT(*) FROM kk_post_term pt JOIN kk_post p ON p.id = pt.post_id
                 WHERE pt.term_id = t.id AND p.status = 'publish') AS n FROM kk_term t WHERE t.taxonomy = ? ORDER BY t.name"
            : 'SELECT t.*, 0 AS n FROM kk_term t WHERE t.taxonomy = ? ORDER BY t.name';
        $st = self::db()->prepare($sql);
        $st->execute([$tax]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Categories in tree order with a depth, for lists and pickers. */
    public static function tree(): array
    {
        $all = self::all('category');
        $by = [];
        foreach ($all as $t) $by[(int)$t['parent']][] = $t;
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $by) {
            foreach ($by[$parent] ?? [] as $t) {
                $t['depth'] = $depth;
                $out[] = $t;
                if ($depth < 6) $walk((int)$t['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        // Orphans (parent deleted) at the end.
        $seen = array_column($out, 'id');
        foreach ($all as $t) if (!in_array($t['id'], $seen)) $out[] = $t + ['depth' => 0];
        return $out;
    }

    public static function get(int $id): ?array
    {
        $st = self::db()->prepare('SELECT * FROM kk_term WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function bySlug(string $tax, string $slug): ?array
    {
        $st = self::db()->prepare('SELECT * FROM kk_term WHERE taxonomy = ? AND slug = ?');
        $st->execute([$tax, $slug]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function defaultCategory(): int
    {
        $id = (int)self::db()->query("SELECT id FROM kk_term WHERE taxonomy = 'category' ORDER BY id LIMIT 1")->fetchColumn();
        return $id ?: self::save(0, 'category', ['name' => 'Notes']);
    }

    /** @throws \InvalidArgumentException */
    public static function save(int $id, string $tax, array $in): int
    {
        $tax = $tax === 'tag' ? 'tag' : 'category';
        $name = mb_substr(trim(strip_tags((string)($in['name'] ?? ''))), 0, 150);
        if ($name === '') throw new \InvalidArgumentException(__('Give it a name.'));
        $slug = Text::slug(trim((string)($in['slug'] ?? '')) !== '' ? $in['slug'] : $name, 140);
        $chk = self::db()->prepare('SELECT id FROM kk_term WHERE taxonomy = ? AND slug = ? AND id <> ?');
        $base = $slug;
        for ($n = 2; ; $n++) {
            $chk->execute([$tax, $slug, $id]);
            if (!$chk->fetchColumn()) break;
            $slug = $base . '-' . $n;
        }
        $parent = $tax === 'category' ? max(0, (int)($in['parent'] ?? 0)) : 0;
        if ($id && $parent === $id) $parent = 0;
        $desc = mb_substr(trim(strip_tags((string)($in['description'] ?? ''))), 0, 2000);
        if ($id) {
            self::db()->prepare('UPDATE kk_term SET name = ?, slug = ?, description = ?, parent = ? WHERE id = ? AND taxonomy = ?')
                ->execute([$name, $slug, $desc, $parent, $id, $tax]);
            return $id;
        }
        $db = self::db();
        $db->prepare('INSERT INTO kk_term (taxonomy, name, slug, description, parent) VALUES (?, ?, ?, ?, ?)')->execute([$tax, $name, $slug, $desc, $parent]);
        return (int)$db->lastInsertId();
    }

    /** Delete a term; posts left without a category go to the first category. */
    public static function delete(int $id): bool
    {
        $t = self::get($id);
        if (!$t) return false;
        $db = self::db();
        if ($t['taxonomy'] === 'category' && (int)$db->query("SELECT COUNT(*) FROM kk_term WHERE taxonomy = 'category'")->fetchColumn() <= 1) {
            throw new \InvalidArgumentException(__('Keep at least one category.'));
        }
        $posts = $db->prepare('SELECT post_id FROM kk_post_term WHERE term_id = ?');
        $posts->execute([$id]);
        $affected = $posts->fetchAll(\PDO::FETCH_COLUMN);
        $db->prepare('DELETE FROM kk_post_term WHERE term_id = ?')->execute([$id]);
        $db->prepare('UPDATE kk_term SET parent = ? WHERE parent = ?')->execute([(int)$t['parent'], $id]);
        $db->prepare('DELETE FROM kk_term WHERE id = ?')->execute([$id]);
        if ($t['taxonomy'] === 'category') {
            $fallback = self::defaultCategory();
            foreach ($affected as $pid) {
                $has = $db->prepare("SELECT COUNT(*) FROM kk_post_term pt JOIN kk_term t ON t.id = pt.term_id WHERE pt.post_id = ? AND t.taxonomy = 'category'");
                $has->execute([$pid]);
                if (!$has->fetchColumn()) $db->prepare('INSERT IGNORE INTO kk_post_term (post_id, term_id) VALUES (?, ?)')->execute([$pid, $fallback]);
            }
        }
        return true;
    }

    /** Tag names ("history, Islamic art") → ids, creating new tags. */
    public static function idsFromNames(string $tax, string $names): array
    {
        $ids = [];
        foreach (array_unique(array_filter(array_map(fn($n) => mb_substr(trim(strip_tags($n)), 0, 150), explode(',', $names)))) as $name) {
            $st = self::db()->prepare('SELECT id FROM kk_term WHERE taxonomy = ? AND (name = ? OR slug = ?) LIMIT 1');
            $st->execute([$tax, $name, Text::slug($name, 140)]);
            $ids[] = (int)$st->fetchColumn() ?: self::save(0, $tax, ['name' => $name]);
        }
        return $ids;
    }

    public static function setForPost(int $postId, string $tax, array $ids): void
    {
        $db = self::db();
        $db->prepare('DELETE FROM kk_post_term WHERE post_id = ? AND term_id IN (SELECT id FROM kk_term WHERE taxonomy = ?)')->execute([$postId, $tax]);
        $ins = $db->prepare('INSERT IGNORE INTO kk_post_term (post_id, term_id) SELECT ?, id FROM kk_term WHERE id = ? AND taxonomy = ?');
        foreach (array_unique($ids) as $tid) $ins->execute([$postId, (int)$tid, $tax]);
    }

    /** ['category' => [...], 'tag' => [...]] for one post. */
    public static function forPost(int $postId): array
    {
        $st = self::db()->prepare('SELECT t.* FROM kk_post_term pt JOIN kk_term t ON t.id = pt.term_id WHERE pt.post_id = ? ORDER BY t.name');
        $st->execute([$postId]);
        $out = ['category' => [], 'tag' => []];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $t) $out[$t['taxonomy']][] = $t;
        return $out;
    }

    public static function url(array $t): string
    {
        return SWB . 'index.php?p=blog&' . ($t['taxonomy'] === 'tag' ? 'tag' : 'category') . '=' . rawurlencode($t['slug']);
    }
}
