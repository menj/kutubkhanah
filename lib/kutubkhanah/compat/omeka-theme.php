<?php
/**
 * Kutubkhanah: Omeka Classic theme bridge. Lets a theme written for Omeka Classic show the library.
 *
 * Books are presented as Omeka "items" with Dublin Core metadata (title, creator, publisher, date, description,
 * identifier, subject, language) and the cover as the item's one file. Supported theme files: index.php (home),
 * items/browse.php (shelf and search results), items/show.php (a book), common/header.php and common/footer.php.
 * Other pages are shown inside the theme's header and footer. Omeka collections, tags, item types, exhibits and
 * simple pages are not available. See docs/themes.md.
 *
 * This file is the template entry point: Theme::file('index_template.inc.php') resolves here for Omeka themes.
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) die('can not access this file directly');

require_once __DIR__ . '/wordpress.php';
require_once __DIR__ . '/omeka.php';

use SLiMS\Kutubkhanah\Hooks;
use SLiMS\Kutubkhanah\Theme;
use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Settings;
use SLiMS\Kutubkhanah\Permalink;

/** An item (book) as Omeka themes expect it. */
class Kk_Omeka_Book extends Kk_Omeka_Item
{
    public array $dc = [];
    public string $image = '';
    public string $url = '';

    public static function fromId(int $id): ?self
    {
        $db = \SLiMS\DB::getInstance();
        $st = $db->prepare('SELECT b.biblio_id, b.title, b.isbn_issn, b.publish_year, b.notes, b.image, b.language_id, p.publisher_name
            FROM biblio b LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id WHERE b.biblio_id = ? AND b.opac_hide = 0');
        $st->execute([$id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$r) return null;
        $a = $db->prepare('SELECT a.author_name FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = ? ORDER BY ba.level');
        $a->execute([$id]);
        $t = $db->prepare('SELECT t.topic FROM biblio_topic bt JOIN mst_topic t ON t.topic_id = bt.topic_id WHERE bt.biblio_id = ?');
        $t->execute([$id]);
        $b = new self((int)$r['biblio_id'], (string)$r['title']);
        $b->dc = ['Title' => [$r['title']], 'Creator' => $a->fetchAll(\PDO::FETCH_COLUMN), 'Publisher' => array_filter([$r['publisher_name']]),
            'Date' => array_filter([$r['publish_year']]), 'Description' => array_filter([trim(strip_tags((string)$r['notes']))]), 'Identifier' => array_filter([$r['isbn_issn']]),
            'Subject' => $t->fetchAll(\PDO::FETCH_COLUMN), 'Language' => array_filter([$r['language_id']]), 'Type' => ['Book']];
        $b->image = (string)$r['image'];
        $b->url = Permalink::book($b->id);
        return $b;
    }
}

class Kk_Omeka_Theme extends Kk_Omeka_View
{
    public static array $loop = ['items' => []];
    public static ?object $current = null;
    public static string $title = '';
    public static string $content = '';
    public static string $context = 'home';
    private static ?self $view = null;

    public static function view(): self { return self::$view ??= new self(); }

    public function render(string $file, array $vars = []): void
    {
        $p = Kk_Wp_Theme_Find::find($file);
        if ($p === '') return;
        extract($vars, EXTR_SKIP);
        include $p;
    }

    public function __call($n, $a) { return ''; }
    public function partial($file, $vars = []) { $this->render((string)$file, (array)$vars); }

    public static function run(string $main, string $title): void
    {
        self::$content = $main;
        self::$title = trim(strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8')));
        $p = (string)($_GET['p'] ?? '');
        if ($p === 'show_detail' && ($b = Kk_Omeka_Book::fromId((int)($_GET['id'] ?? 0)))) {
            self::$context = 'show'; self::$current = $b; self::$loop['items'] = [$b];
            $tpl = 'items/show.php';
        } elseif ($p === '' && !isset($_GET['search'])) {
            self::$context = 'home';
            self::$loop['items'] = array_values(array_filter(array_map(fn($c) => Kk_Omeka_Book::fromId((int)$c['biblio_id']), Library::books('1=1', [], 'b.input_date DESC, b.biblio_id DESC', 10))));
            $tpl = 'index.php';
        } elseif ($p === 'shelf') {
            self::$context = 'browse';
            self::$loop['items'] = array_values(array_filter(array_map(fn($c) => Kk_Omeka_Book::fromId((int)$c['biblio_id']), Library::books('1=1', [], 'b.title', 60))));
            $tpl = 'items/browse.php';
        } else {
            self::$context = 'page';
            $tpl = '';
        }
        Hooks::action('init');
        $view = self::view();
        $file = $tpl !== '' ? Kk_Wp_Theme_Find::find($tpl) : '';
        if ($file === '') {
            echo head(['title' => self::$title]);
            echo '<div id="primary"><h1>' . html_escape(self::$title) . '</h1>' . $main . '</div>';
            echo foot();
            return;
        }
        $vars = self::$context === 'show' ? ['item' => self::$current] : (self::$context === 'home' ? [] : ['items' => self::$loop['items'], 'total_results' => count(self::$loop['items'])]);
        $view->render($tpl, $vars);
    }
}

/** Child-first lookup shared with the WordPress bridge. */
class Kk_Wp_Theme_Find
{
    public static function find(string $rel): string
    {
        foreach (Theme::chain() as $slug) {
            $p = rtrim(SB, '/') . '/template/' . $slug . '/' . $rel;
            if (is_file($p)) return $p;
        }
        return '';
    }
}

/* ---------------- Layout ---------------- */
function head($vars = [], $file = 'header')
{
    ob_start();
    Kk_Omeka_Theme::view()->render('common/' . $file . '.php', (array)$vars + ['title' => '', 'bodyclass' => '', 'bodyid' => '']);
    return (string)ob_get_clean();
}
function foot($vars = [], $file = 'footer') { ob_start(); Kk_Omeka_Theme::view()->render('common/' . $file . '.php', (array)$vars); return (string)ob_get_clean(); }
function common($file, $vars = [], $dir = 'common') { ob_start(); Kk_Omeka_Theme::view()->render($dir . '/' . $file . '.php', (array)$vars); return (string)ob_get_clean(); }
function option($name) { return match ($name) { 'site_title' => get_bloginfo('name'), 'description' => get_bloginfo('description'), 'author' => get_bloginfo('name'), 'copyright' => '© ' . date('Y') . ' ' . get_bloginfo('name'), default => get_option($name, '') }; }
function settings($n) { return option($n); }
function get_theme_option($name, $theme = null) { return get_option('theme_' . Theme::active() . '_' . $name, ''); }
function set_theme_option($name, $value, $theme = null) { return update_option('theme_' . Theme::active() . '_' . $name, $value); }
function theme_logo($file = null) { return ''; }
function head_css($includeDefaults = true)
{
    global $sysconf;
    $o = '<link rel="stylesheet" href="' . esc_attr(SWB) . 'css/kutubkhanah/theme.css?v=' . KUTUBKHANAH_VERSION . '"><style>' . Settings::cssVars('body') . '</style>' . "\n";
    foreach (Theme::chain() as $slug) if (is_file(rtrim(SB, '/') . '/template/' . $slug . '/css/style.css')) { $o .= '<link rel="stylesheet" href="' . esc_attr($sysconf['template']['dir'] . '/' . $slug . '/css/style.css') . '">' . "\n"; break; }
    return $o;
}
function head_js($includeDefaults = true) { return '<script src="' . esc_attr(Theme::asset('js/jquery.min.js')) . '"></script>' . "\n"; }
function auto_discovery_link_tags() { return Settings::get('enable_rss') ? '<link rel="alternate" type="application/rss+xml" title="' . esc_attr(get_bloginfo('name')) . '" href="' . esc_url(home_url(Permalink::enabled() ? 'feed.xml' : 'index.php?p=kk-rss')) . '">' : ''; }
function body_tag($attrs = []) { $a = ''; foreach ((array)$attrs as $k => $v) if ($v !== '' && $v !== null) $a .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"'; return '<body' . $a . '>'; }
function current_user() { return null; }
function is_admin_theme() { return false; }
function js_escape($s) { return json_encode((string)$s, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); }

/* ---------------- Navigation ---------------- */
function link_to_home_page($text = null, $props = [], $action = 'index') { return '<a href="' . esc_url(home_url('/')) . '">' . ($text ?? esc_html(get_bloginfo('name'))) . '</a>'; }
function link_to_items_browse($text = null, $props = [], $action = 'browse') { return '<a href="' . esc_url(home_url(Permalink::enabled() ? 'shelf' : 'index.php?p=shelf')) . '">' . ($text ?? esc_html__('Browse Items')) . '</a>'; }
function public_nav_main($navLinks = null, $maxDepth = 0)
{
    $s = Settings::all();
    $items = [[__('Browse Items'), home_url(Permalink::enabled() ? 'shelf' : 'index.php?p=shelf')]];
    foreach (['series' => __('Series'), 'lists' => __('Reading lists'), 'blog' => (string)($s['blog_title'] ?: __('Journal')), 'faq' => __('FAQ'), 'contact' => __('Contact')] as $p => $label) {
        if (($p === 'lists' && !$s['enable_lists']) || ($p === 'blog' && !$s['blog_enable']) || ($p === 'contact' && !$s['enable_contact'])) continue;
        $to = Permalink::toPretty(['p' => $p]);
        $items[] = [$label, home_url(Permalink::enabled() && $to ? $to[0] : 'index.php?p=' . $p)];
    }
    $li = '';
    foreach ($items as [$l, $u]) $li .= '<li><a href="' . esc_url($u) . '">' . esc_html($l) . '</a></li>';
    return '<ul class="navigation">' . $li . '</ul>';
}
function public_nav_items($navLinks = null, $maxDepth = 0) { return public_nav_main(); }
function search_form($options = []) { return '<form id="search-form" name="search-form" action="' . esc_url(home_url('/')) . '" method="get"><input type="text" name="keywords" id="query" value="' . get_search_query() . '"><input type="hidden" name="search" value="Search"><button type="submit">' . esc_html__('Search') . '</button></form>'; }
if (!function_exists('get_search_query')) { function get_search_query($escaped = true) { return esc_attr((string)($_GET['keywords'] ?? '')); } }
function pagination_links($options = []) { return ''; }
function is_current_url($url) { return false; }

/* ---------------- Records and loops ---------------- */
function loop($type, $records = null)
{
    $type = strtolower((string)$type);
    if ($records !== null) Kk_Omeka_Theme::$loop[$type] = array_values((array)$records);
    foreach (Kk_Omeka_Theme::$loop[$type] ?? [] as $r) {
        Kk_Omeka_Theme::$current = $r;
        yield $r;
    }
}
function has_loop_records($type) { return !empty(Kk_Omeka_Theme::$loop[strtolower((string)$type)]); }
function set_loop_records($type, $records) { Kk_Omeka_Theme::$loop[strtolower((string)$type)] = array_values((array)$records); }
function get_loop_records($type, $throw = true) { return Kk_Omeka_Theme::$loop[strtolower((string)$type)] ?? []; }
function get_current_record($type = 'item', $throw = true) { return Kk_Omeka_Theme::$current; }
function set_current_record($type, $record, $reset = false) { Kk_Omeka_Theme::$current = $record; }
function total_records($type) { return count(Kk_Omeka_Theme::$loop[strtolower((string)$type) . 's'] ?? Kk_Omeka_Theme::$loop['items']); }
function get_recent_items($n = 10) { return array_slice(Kk_Omeka_Theme::$loop['items'], 0, (int)$n); }
function get_random_featured_items($n = 5, $hasImage = null) { return []; }
function get_recent_collections($n = 10) { return []; }
function get_records($type, $params = [], $limit = 10) { return $type === 'Item' ? array_slice(Kk_Omeka_Theme::$loop['items'], 0, (int)$limit) : []; }
function metadata($record, $metadata, $options = [])
{
    $record = is_object($record) ? $record : Kk_Omeka_Theme::$current;
    if (!$record instanceof Kk_Omeka_Book) return '';
    $field = is_array($metadata) ? (string)end($metadata) : (string)$metadata;
    $vals = $record->dc[$field] ?? [];
    if (!$vals && $field === 'id') return $record->id;
    $options = is_string($options) || is_int($options) ? ['index' => $options] : (array)$options;
    $esc = fn($s) => empty($options['no_escape']) ? html_escape($s) : $s;
    if (!empty($options['all'])) return array_map($esc, $vals);
    if (isset($options['index'])) return $esc($vals[(int)$options['index']] ?? '');
    if (!empty($options['delimiter'])) return implode((string)$options['delimiter'], array_map($esc, $vals));
    $v = (string)($vals[0] ?? '');
    if (!empty($options['snippet'])) $v = mb_strlen($v) > (int)$options['snippet'] ? mb_substr($v, 0, (int)$options['snippet']) . '…' : $v;
    return $esc($v);
}
function record_url($record, $action = null, $getAbsoluteUrl = false) { return $record instanceof Kk_Omeka_Book ? $record->url : home_url('/'); }
function link_to($record, $action = null, $text = null, $props = [], $queryParams = [])
{
    $a = '';
    foreach ((array)$props as $k => $v) $a .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
    return '<a href="' . esc_url(record_url($record)) . '"' . $a . '>' . ($text ?? metadata($record, 'Title')) . '</a>';
}
function link_to_item($text = null, $props = [], $action = 'show', $item = null) { return link_to($item ?: Kk_Omeka_Theme::$current, $action, $text, $props); }
function item_has_thumbnail($item = null) { return !empty(($item ?: Kk_Omeka_Theme::$current)->image); }
function item_image($imageType = null, $props = [], $index = 0, $item = null)
{
    $item = $item ?: Kk_Omeka_Theme::$current;
    if (!$item instanceof Kk_Omeka_Book || $item->image === '') return '';
    $w = $imageType === 'thumbnail' ? 200 : ($imageType === 'square_thumbnail' ? 200 : 500);
    $a = '';
    foreach ((array)$props as $k => $v) $a .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
    return '<img src="' . esc_url(home_url('lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode(basename($item->image)) . '&width=' . $w)) . '" alt="' . esc_attr($item->title) . '"' . $a . '>';
}
function files_for_item($options = [], $wrapperAttributes = [], $item = null) { return item_image('fullsize', [], 0, $item); }
function tag_string($records = null, $link = 'items/browse', $delimiter = null) { return ''; }
function item_type_metadata($f) { return ''; }
function plugin_append_to_items_show() { }
function get_specific_plugin_hook_output($plugin, $hook, array $args = []) { return ''; }
function theme_path_info() { return ''; }

Kk_Omeka_Theme::run((string)($main_content ?? ''), (string)($page_title ?? ''));
