<?php
/**
 * Kutubkhanah: SEO permalinks. Public pages get readable addresses such as /book/dune or /series/dune-chronicles
 * instead of index.php?p=show_detail&id=12, following Google's advice on simple, descriptive URLs.
 *
 *  - Slugs for books, authors and series live in kk_slug and never change once made, so shared links keep working.
 *  - Incoming pretty addresses are translated back to the ?p= form before SLiMS routes the page.
 *  - Old ?p= addresses redirect (301) to the pretty ones.
 *  - Every link the page prints is rewritten on output, so no template needs to know about permalinks.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Permalink
{
    /** page name => path */
    private const STATIC_PATHS = [
        'shelf' => 'shelf', 'series' => 'series', 'lists' => 'lists', 'wishlist' => 'wishlist',
        'inside' => 'search-inside', 'contact' => 'contact', 'request_access' => 'request-access', 'faq' => 'faq',
        'kk_rss' => 'feed.xml', 'blog_rss' => 'journal/feed.xml', 'kk_sitemap' => 'sitemap.xml', 'kk_robots' => 'robots.txt',
    ];

    /** Parameters that mean "this is not a normal page view": never redirect these. */
    private const NO_REDIRECT = ['inXML', 'inJSON', 'JSONLD', 'json-ld', 'MARC', 'sec', 'destination', 'logout', 'preview', 'inline'];

    private static array $cache = [];
    public static bool $active = false;

    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    public static function enabled(): bool
    {
        return Settings::get('permalinks') !== 'plain';
    }

    /* ---------- Slugs ---------- */

    public static function slugify(string $s, int $max = 80): string
    {
        $s = mb_strtolower(trim(strip_tags(html_entity_decode($s, ENT_QUOTES, 'UTF-8'))));
        $s = trim((string)preg_replace('/[^\p{L}\p{N}]+/u', '-', $s), '-');
        if (mb_strlen($s) > $max) $s = trim(mb_substr($s, 0, $max), '-');
        return $s;
    }

    /** @param array<int,string|int> $refs @return array<string,string> ref => slug (made when missing) */
    public static function slugs(string $kind, array $refs): array
    {
        $refs = array_values(array_unique(array_map('strval', array_filter($refs, fn($r) => $r !== '' && $r !== null))));
        $out = [];
        $need = [];
        foreach ($refs as $r) {
            if (isset(self::$cache[$kind][$r])) $out[$r] = self::$cache[$kind][$r]; else $need[] = $r;
        }
        $db = self::db();
        foreach (array_chunk($need, 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $st = $db->prepare("SELECT ref, slug FROM kk_slug WHERE kind = ? AND main = 1 AND ref IN ($in)");
                $st->execute(array_merge([$kind], $chunk));
            } catch (\PDOException $e) {
                // Upgraded database that has not seen the 1.8.0 table yet: create it and retry once.
                global $dbs;
                Schema::ensure($dbs);
                $st = $db->prepare("SELECT ref, slug FROM kk_slug WHERE kind = ? AND main = 1 AND ref IN ($in)");
                $st->execute(array_merge([$kind], $chunk));
            }
            foreach ($st->fetchAll(\PDO::FETCH_NUM) as [$ref, $slug]) { $out[(string)$ref] = $slug; self::$cache[$kind][(string)$ref] = $slug; }
        }
        $missing = array_values(array_filter($need, fn($r) => !isset($out[$r])));
        if ($missing) {
            $titles = self::titles($kind, $missing);
            foreach ($missing as $r) {
                $out[$r] = self::$cache[$kind][$r] = self::create($kind, $r, $titles[$r] ?? '');
            }
        }
        return $out;
    }

    private static function titles(string $kind, array $refs): array
    {
        if ($kind === 'series') return array_combine($refs, $refs);
        [$sql, $label] = $kind === 'author'
            ? ['SELECT author_id, author_name FROM mst_author WHERE author_id IN (%s)', 'author']
            : ['SELECT biblio_id, title FROM biblio WHERE biblio_id IN (%s)', 'book'];
        $out = [];
        foreach (array_chunk($refs, 400) as $chunk) {
            $st = self::db()->prepare(sprintf($sql, implode(',', array_fill(0, count($chunk), '?'))));
            $st->execute(array_map('intval', $chunk));
            foreach ($st->fetchAll(\PDO::FETCH_NUM) as [$id, $t]) $out[(string)$id] = (string)$t;
        }
        return $out;
    }

    private static function create(string $kind, string $ref, string $title): string
    {
        $db = self::db();
        $base = self::slugify($title) ?: ($kind . '-' . $ref);
        $slug = $base;
        $exists = $db->prepare('SELECT 1 FROM kk_slug WHERE kind = ? AND slug = ?');
        for ($i = 2; $i < 200; $i++) {
            $exists->execute([$kind, $slug]);
            if (!$exists->fetchColumn()) break;
            $slug = mb_substr($base, 0, 74) . '-' . $i;
        }
        $db->prepare('INSERT INTO kk_slug (kind, ref, slug, main) VALUES (?, ?, ?, 1)')->execute([$kind, $ref, $slug]);
        return $slug;
    }

    /** The thing a slug points at, and the slug that should be used for it. @return array{0:string,1:string}|null [ref, canonicalSlug] */
    public static function resolve(string $kind, string $slug): ?array
    {
        $st = self::db()->prepare('SELECT ref FROM kk_slug WHERE kind = ? AND slug = ?');
        $st->execute([$kind, $slug]);
        $ref = $st->fetchColumn();
        if ($ref === false) return null;
        $canon = self::slugs($kind, [(string)$ref])[(string)$ref] ?? $slug;
        return [(string)$ref, $canon];
    }

    /** After two books are merged, the old address should land on the kept one. */
    public static function moveBook(int $from, int $to): void
    {
        self::db()->prepare("UPDATE kk_slug SET ref = ?, main = 0 WHERE kind = 'book' AND ref = ?")->execute([(string)$to, (string)$from]);
        self::$cache = [];
    }

    /* ---------- Plain <-> pretty ---------- */

    /**
     * ?p= parameters to a pretty path.
     * @return array{0:string,1:array}|null [path, parameters that stay in the query string]
     */
    public static function toPretty(array $q): ?array
    {
        $p = (string)($q['p'] ?? '');
        if ($p === '') return null;
        unset($q['p']);
        $take = function (string ...$keys) use (&$q) { foreach ($keys as $k) unset($q[$k]); };
        switch ($p) {
            case 'show_detail':
                if (!isset($q['id']) || !ctype_digit((string)$q['id']) || (int)$q['id'] < 1) return null;
                $slug = self::slugs('book', [$q['id']])[(string)$q['id']] ?? null;
                if (!$slug) return null;
                $take('id');
                return ['book/' . rawurlencode($slug), $q];
            case 'author':
                if (!isset($q['id']) || !ctype_digit((string)$q['id']) || (int)$q['id'] < 1) return null;
                $slug = self::slugs('author', [$q['id']])[(string)$q['id']] ?? null;
                if (!$slug) return null;
                $take('id');
                return ['author/' . rawurlencode($slug), $q];
            case 'series':
                if (isset($q['name']) && trim((string)$q['name']) !== '') {
                    $slug = self::slugs('series', [trim((string)$q['name'])])[trim((string)$q['name'])] ?? null;
                    if (!$slug) return null;
                    $take('name');
                    return ['series/' . rawurlencode($slug), $q];
                }
                return ['series', $q];
            case 'lists':
                if (!empty($q['list'])) { $s = (string)$q['list']; $take('list'); return ['lists/' . rawurlencode($s), $q]; }
                return ['lists', $q];
            case 'page':
                if (empty($q['slug'])) return null;
                $s = (string)$q['slug']; $take('slug');
                return ['page/' . rawurlencode($s), $q];
            case 'blog':
                if (!empty($q['post'])) { $s = (string)$q['post']; $take('post'); return ['journal/' . rawurlencode($s), $q]; }
                if (!empty($q['category'])) { $s = (string)$q['category']; $take('category'); return ['journal/category/' . rawurlencode($s), $q]; }
                if (!empty($q['tag'])) { $s = (string)$q['tag']; $take('tag'); return ['journal/tag/' . rawurlencode($s), $q]; }
                if (!empty($q['month']) && preg_match('/^\d{4}-\d{2}$/', (string)$q['month'])) { $s = (string)$q['month']; $take('month'); return ['journal/archive/' . $s, $q]; }
                return ['journal', $q];
        }
        if (isset(self::STATIC_PATHS[$p])) return [self::STATIC_PATHS[$p], $q];
        return null;
    }

    /** Pretty path segments to ?p= parameters, or null when the path is not one of ours. */
    private static function fromPretty(string $path): ?array
    {
        $seg = array_map('rawurldecode', explode('/', trim($path, '/')));
        $n = count($seg);
        $head = $seg[0] ?? '';
        $flip = array_flip(array_filter(self::STATIC_PATHS, fn($v) => !str_contains($v, '/')));
        $moveOnly = fn($kind, $s) => self::resolve($kind, $s);

        if ($n === 1 && isset($flip[$head])) return ['p' => $flip[$head]];
        if ($head === 'journal') {
            if ($n === 1) return ['p' => 'blog'];
            if ($n === 2 && $seg[1] === 'feed.xml') return ['p' => 'blog_rss'];
            if ($n === 3 && $seg[1] === 'category') return ['p' => 'blog', 'category' => $seg[2]];
            if ($n === 3 && $seg[1] === 'tag') return ['p' => 'blog', 'tag' => $seg[2]];
            if ($n === 3 && $seg[1] === 'archive') return ['p' => 'blog', 'month' => $seg[2]];
            if ($n === 2) return ['p' => 'blog', 'post' => $seg[1]];
            return null;
        }
        if ($n === 2) {
            switch ($head) {
                case 'book':   $r = $moveOnly('book', $seg[1]);   return ['p' => 'show_detail', 'id' => $r ? (int)$r[0] : 0, '_canon' => $r ? 'book/' . rawurlencode($r[1]) : ''];
                case 'author': $r = $moveOnly('author', $seg[1]); return ['p' => 'author', 'id' => $r ? (int)$r[0] : 0, '_canon' => $r ? 'author/' . rawurlencode($r[1]) : ''];
                case 'series': $r = $moveOnly('series', $seg[1]); return ['p' => 'series', 'name' => $r ? $r[0] : "\x01", '_canon' => $r ? 'series/' . rawurlencode($r[1]) : ''];
                case 'lists':  return ['p' => 'lists', 'list' => $seg[1]];
                case 'page':   return ['p' => 'page', 'slug' => $seg[1]];
            }
        }
        return null;
    }

    /* ---------- Request handling ---------- */

    public static function baseUrl(): string
    {
        return Feed::baseUrl();
    }

    private static function redirect(string $to, int $code = 301): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code($code);
        header('Location: ' . $to);
        header('Cache-Control: public, max-age=3600');
        exit;
    }

    /** Called from index.php before anything reads $_GET. */
    public static function boot(): void
    {
        if (PHP_SAPI === 'cli' || !self::enabled()) return;
        self::$active = true;
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)parse_url($uri, PHP_URL_PATH);
        $root = (string)parse_url((string)SWB, PHP_URL_PATH) ?: '/';
        $rel = str_starts_with($path, $root) ? substr($path, strlen($root)) : ltrim($path, '/');
        $rel = trim($rel, '/');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($rel !== '' && $rel !== 'index.php') {
            if (str_ends_with($rel, '.php') || str_contains($rel, '..')) { self::filter(); return; }
            $params = self::fromPretty($rel);
            if ($params !== null) {
                $canon = $params['_canon'] ?? null;
                unset($params['_canon']);
                if ($canon !== null && $canon !== '' && $canon !== rawurlencode(rawurldecode($rel)) && $canon !== $rel && in_array($method, ['GET', 'HEAD'], true)) {
                    $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
                    self::redirect(self::baseUrl() . $canon . ($qs !== '' ? '?' . $qs : ''));
                }
                foreach ($params as $k => $v) { $_GET[$k] = $v; $_REQUEST[$k] = $v; }
                $_SERVER['QUERY_STRING'] = http_build_query($_GET);
            }
            self::filter();
            return;
        }

        if (in_array($method, ['GET', 'HEAD'], true) && isset($_GET['p']) && is_string($_GET['p'])
            && !array_intersect(array_keys($_GET), self::NO_REDIRECT)) {
            $to = self::toPretty($_GET);
            if ($to !== null) {
                self::redirect(self::baseUrl() . $to[0] . ($to[1] ? '?' . http_build_query($to[1]) : ''));
            }
        }
        self::filter();
    }

    private static function filter(): void
    {
        ob_start([self::class, 'rewrite']);
    }

    /** Output filter: index.php?p=... links become pretty links. */
    public static function rewrite(string $html): string
    {
        if (!str_contains($html, 'index.php?p=')) return $html;
        $re = '~([^"\'\s<>(=]*?)index\.php\?(p=[^"\'\s<>#]*)~';
        if (!preg_match_all($re, $html, $m, PREG_SET_ORDER)) return $html;

        $parsed = [];
        $want = ['book' => [], 'author' => [], 'series' => []];
        foreach ($m as $hit) {
            $raw = $hit[2];
            $amp = str_contains($raw, '&amp;');
            parse_str(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), $q);
            $parsed[$hit[0]] = [$q, $amp];
            $p = $q['p'] ?? '';
            if ($p === 'show_detail' && isset($q['id']) && ctype_digit((string)$q['id'])) $want['book'][] = (string)$q['id'];
            if ($p === 'author' && isset($q['id']) && ctype_digit((string)$q['id'])) $want['author'][] = (string)$q['id'];
            if ($p === 'series' && !empty($q['name'])) $want['series'][] = trim((string)$q['name']);
        }
        try {
            foreach ($want as $kind => $refs) if ($refs) self::slugs($kind, $refs);
            return preg_replace_callback($re, function ($x) use ($parsed) {
                [$q, $amp] = $parsed[$x[0]] ?? [[], false];
                if (!$q || array_intersect(array_keys($q), self::NO_REDIRECT)) return $x[0];
                $to = self::toPretty($q);
                if ($to === null) return $x[0];
                $extra = $to[1] ? '?' . http_build_query($to[1], '', $amp ? '&amp;' : '&') : '';
                $pre = $x[1] === '' ? (string)SWB : $x[1];
                return $pre . $to[0] . $extra;
            }, $html) ?? $html;
        } catch (\Throwable $e) {
            error_log('Kutubkhanah permalink: ' . $e->getMessage());
            return $html;
        }
    }

    /** Pretty URL for a book, from Kutubkhanah's own code (feeds, API, sitemap). */
    public static function book(int $id): string
    {
        $base = self::baseUrl();
        if (!self::enabled()) return $base . 'index.php?p=show_detail&id=' . $id;
        $to = self::toPretty(['p' => 'show_detail', 'id' => (string)$id]);
        return $base . ($to ? $to[0] : 'index.php?p=show_detail&id=' . $id);
    }

    /** Canonical absolute URL of the page being served, or ''. */
    public static function canonical(): string
    {
        if (!self::enabled() || empty($_GET['p'])) return '';
        $keep = ['p' => $_GET['p']];
        foreach (['id', 'name', 'list', 'slug', 'post', 'category', 'tag', 'month'] as $k) if (isset($_GET[$k])) $keep[$k] = $_GET[$k];
        $to = self::toPretty($keep);
        return $to ? self::baseUrl() . $to[0] : '';
    }
}
