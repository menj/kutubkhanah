<?php
/**
 * Kutubkhanah: search-engine basics for the public site (canonical address, structured data, sitemap, robots.txt),
 * following Google's SEO Starter Guide and structured-data documentation.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Seo
{
    private static function db(): \PDO { return \SLiMS\DB::getInstance(); }

    private static function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

    /** <link rel="canonical"> plus JSON-LD for the page being served. */
    public static function head(): string
    {
        global $sysconf;
        $out = '';
        $canon = Permalink::canonical();
        if ($canon === '' && empty($_GET['p'])) $canon = Feed::baseUrl();
        if ($canon !== '') $out .= '    <link rel="canonical" href="' . self::e($canon) . '">' . "\n";
        $p = (string)($_GET['p'] ?? '');
        if (Settings::get('enable_rss')) $out .= '    <link rel="alternate" type="application/rss+xml" title="' . self::e((string)($sysconf['library_name'] ?? '') . ' · ' . __('New additions')) . '" href="' . self::e(Permalink::enabled() ? Feed::baseUrl() . 'feed.xml' : Feed::baseUrl() . 'index.php?p=kk-rss') . '">' . "\n";
        $graph = [];
        try {
            if ($p === '') $graph = self::site();
            elseif ($p === 'show_detail') $graph = self::book((int)($_GET['id'] ?? 0), $canon);
            elseif ($p === 'blog' && !empty($_GET['post'])) $graph = self::article((string)$_GET['post'], $canon);
        } catch (\Throwable $e) { error_log('Kutubkhanah seo: ' . $e->getMessage()); }
        if ($graph) $out .= '    <script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
        return $out;
    }

    private static function crumbs(array $trail): array
    {
        $items = [];
        foreach ($trail as $i => [$name, $url]) $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    private static function site(): array
    {
        global $sysconf;
        $base = Feed::baseUrl();
        $name = (string)($sysconf['library_name'] ?? 'Kutubkhanah');
        $g = [['@type' => 'WebSite', 'name' => $name, 'url' => $base,
            'potentialAction' => ['@type' => 'SearchAction', 'target' => $base . (Permalink::enabled() ? 'shelf' : 'index.php?p=shelf') . (Permalink::enabled() ? '?' : '&') . 'keywords={search_term_string}', 'query-input' => 'required name=search_term_string']],
            ['@type' => 'Library', 'name' => $name, 'url' => $base]];
        return $g;
    }

    private static function book(int $id, string $canon): array
    {
        if ($id < 1) return [];
        $db = self::db();
        $st = $db->prepare('SELECT b.title, b.isbn_issn, b.publish_year, b.notes, b.image, b.language_id, p.publisher_name
            FROM biblio b LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id WHERE b.biblio_id = ?');
        $st->execute([$id]);
        $b = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$b) return [];
        $a = $db->prepare('SELECT a.author_name FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = ? ORDER BY ba.level');
        $a->execute([$id]);
        $authors = array_map(fn($n) => ['@type' => 'Person', 'name' => $n], $a->fetchAll(\PDO::FETCH_COLUMN));
        $base = Feed::baseUrl();
        $book = ['@type' => 'Book', '@id' => $canon . '#book', 'name' => $b['title'], 'url' => $canon];
        if ($authors) $book['author'] = $authors;
        if ($b['isbn_issn']) $book['isbn'] = $b['isbn_issn'];
        if ($b['publisher_name']) $book['publisher'] = ['@type' => 'Organization', 'name' => $b['publisher_name']];
        if ($b['publish_year']) $book['datePublished'] = $b['publish_year'];
        if ($b['language_id']) $book['inLanguage'] = $b['language_id'];
        if (trim((string)$b['notes']) !== '') $book['description'] = mb_substr(trim(strip_tags($b['notes'])), 0, 300);
        if ($b['image']) $book['image'] = $base . 'images/docs/' . rawurlencode($b['image']);
        return [$book, self::crumbs([[__('Library'), $base], [__('Books'), $base . (Permalink::enabled() ? 'shelf' : 'index.php?p=shelf')], [$b['title'], $canon]])];
    }

    private static function article(string $slug, string $canon): array
    {
        $post = \SLiMS\Kutubkhanah\Blog\Posts::bySlug('post', $slug);
        if (!$post) return [];
        $base = Feed::baseUrl();
        $art = ['@type' => 'BlogPosting', 'headline' => mb_substr((string)$post['title'], 0, 110), 'mainEntityOfPage' => $canon, 'url' => $canon];
        if (!empty($post['published_at'])) $art['datePublished'] = date('c', strtotime($post['published_at']));
        if (!empty($post['author_name'])) $art['author'] = ['@type' => 'Person', 'name' => $post['author_name']];
        if (!empty($GLOBALS['kk_meta']['image'])) $art['image'] = $base . ltrim((string)$GLOBALS['kk_meta']['image'], '/');
        return [$art, self::crumbs([[__('Library'), $base], [__('Journal'), $base . (Permalink::enabled() ? 'journal' : 'index.php?p=blog')], [$post['title'], $canon]])];
    }

    /* ---------- sitemap.xml and robots.txt ---------- */

    public static function sitemap(): void
    {
        $base = Feed::baseUrl();
        $db = self::db();
        $pl = Permalink::enabled();
        $u = [];
        $add = function (string $loc, ?string $mod = null) use (&$u) { $u[] = [$loc, $mod]; };
        $add($base);
        foreach (['shelf' => 1, 'series' => Settings::get('enable_lists') ? 1 : 1, 'lists' => (int)Settings::get('enable_lists'), 'faq' => 1, 'contact' => (int)Settings::get('enable_contact'), 'wishlist' => (int)Settings::get('enable_wishlist'), 'inside' => (int)Settings::get('enable_inside'), 'blog' => (int)Settings::get('blog_enable')] as $p => $on) {
            if (!$on) continue;
            $to = Permalink::toPretty(['p' => $p]);
            $add($pl && $to ? $base . $to[0] : $base . 'index.php?p=' . $p);
        }
        $ids = $db->query('SELECT biblio_id, last_update FROM biblio ORDER BY biblio_id')->fetchAll(\PDO::FETCH_NUM);
        $slugs = $pl ? Permalink::slugs('book', array_column($ids, 0)) : [];
        foreach ($ids as [$id, $lu]) $add($pl ? $base . 'book/' . rawurlencode($slugs[(string)$id] ?? '') : $base . 'index.php?p=show_detail&id=' . $id, $lu ? date('c', strtotime($lu)) : null);
        $au = $db->query('SELECT DISTINCT a.author_id FROM mst_author a JOIN biblio_author ba ON ba.author_id = a.author_id')->fetchAll(\PDO::FETCH_COLUMN);
        $as = $pl ? Permalink::slugs('author', $au) : [];
        foreach ($au as $id) $add($pl ? $base . 'author/' . rawurlencode($as[(string)$id] ?? '') : $base . 'index.php?p=author&id=' . $id);
        if (Settings::get('enable_lists')) {
            foreach ($db->query("SELECT slug FROM kk_list WHERE is_public = 1 AND slug <> ''")->fetchAll(\PDO::FETCH_COLUMN) as $s) $add($pl ? $base . 'lists/' . rawurlencode($s) : $base . 'index.php?p=lists&list=' . rawurlencode($s));
        }
        if (Settings::get('blog_enable')) {
            try {
                foreach (\SLiMS\Kutubkhanah\Blog\Posts::sitemapRows() as [$s, $d]) $add($pl ? $base . 'journal/' . rawurlencode($s) : $base . 'index.php?p=blog&post=' . rawurlencode($s), $d ? date('c', strtotime($d)) : null);
            } catch (\Throwable $e) { error_log('Kutubkhanah sitemap: ' . $e->getMessage()); }
        }
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array_slice($u, 0, 50000) as [$loc, $mod]) $x .= '<url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . "</url>\n";
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $x . "</urlset>\n";
        exit;
    }

    public static function robots(): void
    {
        global $sysconf;
        $base = Feed::baseUrl();
        $t = "User-agent: *\n";
        if (!empty($sysconf['kk_discourage_search'])) {
            $t .= "Disallow: /\n";
        } else {
            $root = (string)parse_url($base, PHP_URL_PATH);
            foreach (['admin/', 'lib/', 'files/', 'install/', 'config/', 'plugins/', 'index.php?p=login', 'index.php?p=librarian', 'index.php?p=member'] as $d) $t .= 'Disallow: ' . $root . $d . "\n";
            $t .= "\nSitemap: " . $base . (Permalink::enabled() ? 'sitemap.xml' : 'index.php?p=kk-sitemap') . "\n";
        }
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $t;
        exit;
    }
}
