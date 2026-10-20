<?php
/**
 * Kutubkhanah: series with volume numbers (after PMB). The series name is the base system's Series Title,
 * the volume number is Book Details > Volume in series. A series page lists the volumes you own
 * and the gaps, up to the total you set for the series.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Series
{
    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    /** "Name ; 3", "Name, v. 3", "Name #3" → ['Name', '3']. */
    public static function split(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/^(.*?)\s*(?:;|,|#|\bv\.|\bvol\.|\bno\.|\bbook|\bjil\.?|\bjilid|\bjuz\'?)\s*(\d+[a-z]?)\s*$/iu', $raw, $m) && trim($m[1]) !== '') {
            return [Marc::clean(trim($m[1])), $m[2]];
        }
        return [Marc::clean($raw), ''];
    }

    /** Every series with how many books you own. */
    public static function all(): array
    {
        $rows = self::db()->query(
            "SELECT b.series_title AS name, COUNT(*) AS owned, MAX(b.biblio_id) AS sample
             FROM biblio b WHERE b.series_title IS NOT NULL AND b.series_title <> '' AND b.opac_hide = 0
             GROUP BY b.series_title ORDER BY b.series_title"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $meta = self::db()->query('SELECT name, total, description FROM kk_series')->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['total'] = (int)($meta[$r['name']]['total'] ?? 0);
            $r['description'] = (string)($meta[$r['name']]['description'] ?? '');
        }
        return $rows;
    }

    public static function info(string $name): array
    {
        $st = self::db()->prepare('SELECT total, description FROM kk_series WHERE name = ?');
        $st->execute([$name]);
        $r = $st->fetch(\PDO::FETCH_ASSOC) ?: ['total' => 0, 'description' => ''];
        return ['name' => $name, 'total' => (int)$r['total'], 'description' => (string)$r['description']];
    }

    /** Books in a series, by volume number (unnumbered last), plus the missing volume numbers. */
    public static function books(string $name, bool $publicOnly = true): array
    {
        $st = self::db()->prepare(
            'SELECT b.biblio_id, b.title, b.image, b.publish_year, b.sor, k.volume
             FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE b.series_title = ?' . ($publicOnly ? ' AND b.opac_hide = 0' : '')
        );
        $st->execute([$name]);
        $books = $st->fetchAll(\PDO::FETCH_ASSOC);
        usort($books, function ($a, $b) {
            $va = $a['volume'] === null || $a['volume'] === '' ? PHP_INT_MAX : (float)$a['volume'];
            $vb = $b['volume'] === null || $b['volume'] === '' ? PHP_INT_MAX : (float)$b['volume'];
            return $va <=> $vb ?: strcasecmp($a['title'], $b['title']);
        });
        $owned = [];
        foreach ($books as $bk) if (preg_match('/^\d+/', (string)$bk['volume'], $m)) $owned[(int)$m[0]] = true;
        $info = self::info($name);
        $upTo = max($info['total'], $owned ? max(array_keys($owned)) : 0);
        $missing = [];
        for ($i = 1; $i <= min($upTo, 500); $i++) if (!isset($owned[$i])) $missing[] = $i;
        return ['info' => $info, 'books' => $books, 'missing' => $missing];
    }

    public static function save(string $name, int $total, string $description): void
    {
        $name = mb_substr(trim($name), 0, 200);
        if ($name === '') return;
        self::db()->prepare('INSERT INTO kk_series (name, total, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE total = VALUES(total), description = VALUES(description)')
            ->execute([$name, max(0, min(500, $total)), mb_substr(trim(strip_tags($description)), 0, 5000)]);
    }

    /** Public page for one series. */
    public static function page(string $name): ?string
    {
        $data = self::books($name);
        if (!$data['books']) return null;
        $e = [Library::class, 'e'];
        $ids = array_map(fn($b) => (int)$b['biblio_id'], $data['books']);
        $cards = Library::books('b.biblio_id IN (' . implode(',', $ids) . ')', [], 'b.title', count($ids));
        $byId = [];
        foreach ($cards as $c) $byId[(int)$c['biblio_id']] = $c + ['show_volume' => 1];
        $ordered = [];
        foreach ($ids as $id) if (isset($byId[$id])) $ordered[] = $byId[$id];

        $owned = count($ordered);
        $total = $data['info']['total'];
        $html = '<div class="kk-series">';
        $summary = $total > 0
            ? sprintf(__('%1$d of %2$d volumes in the collection'), $owned, $total)
            : sprintf($owned === 1 ? __('%d book in the collection') : __('%d books in the collection'), $owned);
        $html .= '<p class="kk-series__count">' . $e($summary) . '</p>';
        if ($data['info']['description'] !== '') $html .= '<p class="kk-series__desc" dir="auto">' . nl2br($e($data['info']['description'])) . '</p>';
        if ($data['missing']) {
            $html .= '<p class="kk-series__missing"><span>' . __('Missing volumes') . '</span> ' . $e(self::ranges($data['missing'])) . '</p>';
        }
        return $html . '</div>' . Library::grid($ordered);
    }

    /** [1,2,3,5,7,8] → "1–3, 5, 7–8". */
    public static function ranges(array $n): string
    {
        sort($n);
        $out = [];
        for ($i = 0, $c = count($n); $i < $c; $i++) {
            $start = $n[$i];
            while ($i + 1 < $c && $n[$i + 1] === $n[$i] + 1) $i++;
            $out[] = $start === $n[$i] ? (string)$start : $start . '–' . $n[$i];
        }
        return implode(', ', $out);
    }

    /** Public list of series. */
    public static function index(): string
    {
        $e = [Library::class, 'e'];
        $rows = array_filter(self::all(), fn($r) => (int)$r['owned'] > 1 || (int)$r['total'] > 0);
        if (!$rows) return '<p class="kk-muted">' . __('No series yet. Give books a Series Title and a volume number to see them here.') . '</p>';
        $out = '<ul class="kk-series-list">';
        foreach ($rows as $r) {
            $count = $r['total'] > 0 ? sprintf(__('%1$d of %2$d'), $r['owned'], $r['total']) : sprintf(__('%d books'), $r['owned']);
            $out .= '<li><a href="' . $e(self::url($r['name'])) . '"><span class="kk-series-list__name" dir="auto">' . $e($r['name']) . '</span>'
                . '<span class="kk-series-list__count">' . $e($count) . '</span></a></li>';
        }
        return $out . '</ul>';
    }

    public static function url(string $name): string
    {
        return SWB . 'index.php?p=series&name=' . rawurlencode($name);
    }
}
