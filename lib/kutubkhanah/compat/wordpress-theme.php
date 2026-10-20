<?php
/**
 * Kutubkhanah: WordPress theme bridge. Lets a theme written for WordPress show the library.
 *
 * The page being served becomes one "post" (a book, a journal post, a page, or the search results), and the home page
 * becomes a list of the newest books, so a theme's index.php, single.php, page.php, header.php and footer.php work with
 * the usual template tags. Anything that needs real WordPress data (comments, menus from the database, widgets, custom
 * post queries, the block editor) is stubbed or empty. See docs/themes.md.
 *
 * This file is the template entry point: Theme::file('index_template.inc.php') resolves here for WordPress themes.
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) die('can not access this file directly');

require_once __DIR__ . '/wordpress.php';

use SLiMS\Kutubkhanah\Hooks;
use SLiMS\Kutubkhanah\Theme;
use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Settings;
use SLiMS\Kutubkhanah\Permalink;

class Kk_Wp_Theme
{
    public static array $posts = [];
    public static int $i = -1;
    public static ?object $post = null;
    public static string $context = 'home';
    public static string $title = '';
    public static string $content = '';

    public static function render(string $mainContent, string $pageTitle): void
    {
        self::$content = $mainContent;
        self::$title = trim(strip_tags(html_entity_decode($pageTitle, ENT_QUOTES, 'UTF-8')));
        $p = (string)($_GET['p'] ?? '');
        $isSearch = isset($_GET['search']);
        if ($p === '' && !$isSearch) {
            self::$context = 'home';
            foreach (Library::books('1=1', [], 'b.input_date DESC, b.biblio_id DESC', 12) as $b) {
                self::$posts[] = (object)[
                    'ID' => (int)$b['biblio_id'], 'post_title' => $b['title'], 'post_name' => $b['biblio_id'],
                    'post_content' => '', 'post_excerpt' => trim(($b['authors'] ?? '') . ($b['publish_year'] ? ' (' . $b['publish_year'] . ')' : '')),
                    'post_type' => 'book', 'post_date' => date('Y-m-d H:i:s'), 'post_author' => 0, 'author_name' => (string)($b['authors'] ?? ''),
                    'permalink' => Permalink::book((int)$b['biblio_id']), 'image' => $b['image'] ?? '',
                ];
            }
        } else {
            self::$context = $isSearch ? 'search' : ($p === 'show_detail' ? 'book' : ($p === 'blog' && !empty($_GET['post']) ? 'post' : ($p === 'blog' ? 'archive' : 'page')));
            $image = '';
            if (self::$context === 'book' && !empty($GLOBALS['image_src'])) $image = basename((string)$GLOBALS['image_src']);
            self::$posts[] = (object)[
                'ID' => (int)($_GET['id'] ?? 0), 'post_title' => self::$title, 'post_name' => (string)($_GET['slug'] ?? $_GET['post'] ?? $p),
                'post_content' => $mainContent, 'post_excerpt' => '', 'post_type' => self::$context === 'post' ? 'post' : (self::$context === 'book' ? 'book' : 'page'),
                'post_date' => date('Y-m-d H:i:s'), 'post_author' => 0, 'author_name' => '', 'permalink' => Permalink::canonical() ?: \SLiMS\Kutubkhanah\Feed::baseUrl(), 'image' => $image,
            ];
        }
        $GLOBALS['wp_query'] = (object)['posts' => self::$posts, 'found_posts' => count(self::$posts), 'max_num_pages' => 1];
        Hooks::add('wp_head', [self::class, 'head'], 1);
        Hooks::add('wp_footer', [self::class, 'foot'], 1);
        Hooks::action('after_setup_theme');
        Hooks::action('init');
        $tpl = self::locateTemplate();
        if ($tpl === '') { echo '<!DOCTYPE html><html><body>' . $mainContent . '</body></html>'; return; }
        Hooks::action('template_redirect');
        include $tpl;
    }

    /** WordPress's template hierarchy, trimmed to what exists here. */
    private static function locateTemplate(): string
    {
        $c = self::$context;
        $slug = (string)(self::$posts[0]->post_name ?? '');
        $cands = match ($c) {
            'home' => ['front-page.php', 'home.php', 'index.php'],
            'book' => ['single-book.php', 'single.php', 'singular.php', 'index.php'],
            'post' => ['single-post.php', 'single.php', 'singular.php', 'index.php'],
            'archive' => ['home.php', 'archive.php', 'index.php'],
            'search' => ['search.php', 'page.php', 'singular.php', 'index.php'],
            default => ['page-' . preg_replace('/[^a-z0-9-]/i', '', $slug) . '.php', 'page.php', 'singular.php', 'index.php'],
        };
        foreach ($cands as $f) { $p = self::find($f); if ($p !== '') return $p; }
        return '';
    }

    /** Child theme file first, then the parent's. */
    public static function find(string $rel): string
    {
        foreach (Theme::chain() as $slug) {
            $p = rtrim(SB, '/') . '/template/' . $slug . '/' . $rel;
            if (is_file($p)) return $p;
        }
        return '';
    }

    public static function head(): void
    {
        echo '<base href="' . esc_attr(\SLiMS\Kutubkhanah\Feed::baseUrl()) . "\">\n";
        if (!empty($GLOBALS['sysconf']['kk_discourage_search'])) echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        echo $GLOBALS['metadata'] ?? '';
        echo \SLiMS\Kutubkhanah\Seo::head();
        echo '<meta name="description" content="' . esc_attr(trim(strip_tags(self::$title . ' – ' . ($GLOBALS['sysconf']['library_subname'] ?? '')), ' –')) . "\">\n";
        if (current_theme_supports('title-tag')) echo '<title>' . esc_html(wp_get_document_title()) . "</title>\n";
        echo '<link rel="stylesheet" href="' . esc_attr(SWB) . 'css/kutubkhanah/theme.css?v=' . KUTUBKHANAH_VERSION . "\">\n";
        echo '<style>' . Settings::cssVars('body') . "</style>\n";
        echo Theme::head();
    }

    public static function foot(): void
    {
        echo '<script src="' . esc_attr(Theme::asset('js/jquery.min.js')) . "\"></script>\n";
        echo '<script src="' . esc_attr(SWB) . 'js/kutubkhanah/theme.js?v=' . KUTUBKHANAH_VERSION . "\"></script>\n";
        echo Theme::footer();
    }
}

/* ---------------- Locating and including template parts ---------------- */
function get_template_directory() { $c = Theme::chain(); return rtrim(SB, '/') . '/template/' . end($c); }
function get_template_directory_uri() { global $sysconf; $c = Theme::chain(); return home_url($sysconf['template']['dir'] . '/' . end($c)); }
function get_stylesheet_directory() { return rtrim(SB, '/') . '/template/' . Theme::active(); }
function get_stylesheet_directory_uri() { global $sysconf; return home_url($sysconf['template']['dir'] . '/' . Theme::active()); }
function get_stylesheet_uri() { return get_stylesheet_directory_uri() . '/style.css'; }
function get_theme_file_path($f = '') { return Kk_Wp_Theme::find(ltrim((string)$f, '/')) ?: get_stylesheet_directory() . '/' . ltrim((string)$f, '/'); }
function get_theme_file_uri($f = '') { return get_stylesheet_directory_uri() . '/' . ltrim((string)$f, '/'); }
function get_parent_theme_file_path($f = '') { return get_template_directory() . '/' . ltrim((string)$f, '/'); }
function get_parent_theme_file_uri($f = '') { return get_template_directory_uri() . '/' . ltrim((string)$f, '/'); }
function locate_template($names, $load = false, $once = true, $args = [])
{
    foreach ((array)$names as $n) {
        $p = Kk_Wp_Theme::find((string)$n);
        if ($p !== '') { if ($load) { $once ? require_once $p : require $p; } return $p; }
    }
    return '';
}
function get_header($name = null, $args = [])
{
    Hooks::action('get_header', $name);
    if (!locate_template($name ? ["header-$name.php", 'header.php'] : ['header.php'], true, false)) {
        echo '<!DOCTYPE html><html ' . language_attributes(false) . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        wp_head();
        echo '</head><body>';
    }
}
function get_footer($name = null, $args = [])
{
    Hooks::action('get_footer', $name);
    if (!locate_template($name ? ["footer-$name.php", 'footer.php'] : ['footer.php'], true, false)) { wp_footer(); echo '</body></html>'; }
}
function get_sidebar($name = null, $args = []) { Hooks::action('get_sidebar', $name); locate_template($name ? ["sidebar-$name.php", 'sidebar.php'] : ['sidebar.php'], true, false); }
function get_template_part($slug, $name = null, $args = [])
{
    Hooks::action('get_template_part_' . $slug, $slug, $name);
    $names = $name ? ["$slug-$name.php", "$slug.php"] : ["$slug.php"];
    locate_template($names, true, false);
}
function wp_body_open() { Hooks::action('wp_body_open'); }

/* ---------------- The Loop ---------------- */
function have_posts() { return Kk_Wp_Theme::$i + 1 < count(Kk_Wp_Theme::$posts); }
function the_post() { Kk_Wp_Theme::$i++; $GLOBALS['post'] = Kk_Wp_Theme::$post = Kk_Wp_Theme::$posts[Kk_Wp_Theme::$i] ?? null; Hooks::action('the_post', $GLOBALS['post']); }
function rewind_posts() { Kk_Wp_Theme::$i = -1; }
function wp_reset_postdata() { }
function wp_reset_query() { }
function get_post($id = null) { return Kk_Wp_Theme::$post ?? (Kk_Wp_Theme::$posts[0] ?? null); }
function get_the_ID() { return (int)(get_post()->ID ?? 0); }
function the_ID() { echo get_the_ID(); }
function get_post_type($p = null) { return (string)(get_post()->post_type ?? ''); }
function get_the_title($p = 0) { return apply_filters('the_title', (string)(get_post()->post_title ?? ''), get_the_ID()); }
function the_title($before = '', $after = '', $echo = true) { $t = get_the_title(); if ($t === '') return ''; $o = $before . $t . $after; if ($echo) echo $o; return $o; }
function the_title_attribute($args = []) { echo esc_attr(strip_tags(get_the_title())); }
function get_permalink($p = 0) { return (string)(get_post()->permalink ?? ''); }
function the_permalink() { echo esc_url(get_permalink()); }
function get_the_content($more = null, $strip = false) { return (string)(get_post()->post_content ?? ''); }
function the_content($more = null, $strip = false) { echo apply_filters('the_content', get_the_content()); }
function get_the_excerpt($p = null) { return (string)(get_post()->post_excerpt ?? ''); }
function the_excerpt() { echo apply_filters('the_excerpt', esc_html(get_the_excerpt())); }
function get_the_date($f = '', $p = 0) { return date($f ?: 'F j, Y', strtotime((string)(get_post()->post_date ?? 'now'))); }
function the_date($f = '', $before = '', $after = '', $echo = true) { $d = $before . get_the_date($f) . $after; if ($echo) echo $d; return $d; }
function get_the_time($f = '', $p = 0) { return date($f ?: 'g:i a', strtotime((string)(get_post()->post_date ?? 'now'))); }
function the_time($f = '') { echo get_the_time($f); }
function get_the_modified_date($f = '') { return get_the_date($f); }
function the_modified_date($f = '') { echo get_the_date($f); }
function get_the_author() { return (string)(get_post()->author_name ?? ''); }
function the_author() { echo esc_html(get_the_author()); }
function get_the_author_meta($f = '', $id = false) { return ''; }
function the_author_meta($f = '', $id = false) { }
function get_author_posts_url($id, $nicename = '') { return home_url('/'); }
function get_the_category($id = 0) { return []; }
function get_the_category_list($sep = '', $p = '', $id = false) { return ''; }
function the_category($sep = '', $p = '', $id = false) { }
function get_the_tags($id = 0) { return false; }
function the_tags($before = null, $sep = ', ', $after = '') { }
function get_the_term_list($id, $tax, $b = '', $s = '', $a = '') { return ''; }
function get_post_meta($id, $key = '', $single = false) { return $single ? '' : []; }
function update_post_meta($id, $k, $v, $prev = '') { return true; }
function has_post_thumbnail($p = null) { return !empty(get_post()->image); }
function get_the_post_thumbnail_url($p = null, $size = 'post-thumbnail') { $i = get_post()->image ?? ''; return $i ? home_url('lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode(basename($i)) . '&width=' . (is_array($size) ? (int)$size[0] : 300)) : false; }
function get_the_post_thumbnail($p = null, $size = 'post-thumbnail', $attr = '') { $u = get_the_post_thumbnail_url($p, $size); return $u ? '<img src="' . esc_url($u) . '" alt="' . esc_attr(get_the_title()) . '" loading="lazy" class="wp-post-image">' : ''; }
function the_post_thumbnail($size = 'post-thumbnail', $attr = '') { echo get_the_post_thumbnail(null, $size, $attr); }
function post_class($c = '', $id = null) { echo 'class="' . esc_attr(implode(' ', get_post_class($c))) . '"'; }
function get_post_class($c = '', $id = null) { $t = get_post_type(); return array_filter(array_merge(['post-' . get_the_ID(), 'type-' . $t, 'hentry'], (array)(is_string($c) ? explode(' ', $c) : $c))); }
function body_class($c = '') { echo 'class="' . esc_attr(implode(' ', get_body_class($c))) . '"'; }
function get_body_class($c = '') { $k = Kk_Wp_Theme::$context; return array_filter(array_merge([$k === 'home' ? 'home' : 'singular', 'kutubkhanah', 'page-' . $k], (array)(is_string($c) ? explode(' ', $c) : $c))); }
function language_attributes($echo = true) { $a = 'lang="' . esc_attr(get_bloginfo('language')) . '"'; if ($echo) echo $a; return $a; }
function wp_get_document_title() { $n = get_bloginfo('name'); return Kk_Wp_Theme::$context === 'home' ? $n : Kk_Wp_Theme::$title . ' | ' . $n; }
function wp_title($sep = '|', $display = true, $loc = '') { $t = Kk_Wp_Theme::$context === 'home' ? '' : ' ' . $sep . ' ' . Kk_Wp_Theme::$title; if ($display) echo esc_html($t); return $t; }
function is_home() { return in_array(Kk_Wp_Theme::$context, ['home', 'archive'], true); }
function is_front_page() { return Kk_Wp_Theme::$context === 'home'; }
function is_single($p = '') { return in_array(Kk_Wp_Theme::$context, ['book', 'post'], true); }
function is_singular($t = '') { return !in_array(Kk_Wp_Theme::$context, ['home', 'archive', 'search'], true) && ($t === '' || in_array(get_post_type(), (array)$t, true)); }
function is_page($p = '') { return Kk_Wp_Theme::$context === 'page'; }
function is_archive() { return Kk_Wp_Theme::$context === 'archive'; }
function is_search() { return Kk_Wp_Theme::$context === 'search'; }
function is_404() { return false; }
function is_category($c = '') { return false; }
function is_tag($t = '') { return false; }
function is_author($a = '') { return false; }
function is_attachment() { return false; }
function is_paged() { return false; }
function is_preview() { return false; }
function is_customize_preview() { return false; }
function is_sticky($id = 0) { return false; }
function post_password_required($p = null) { return false; }
function comments_open($p = null) { return false; }
function pings_open($p = null) { return false; }
function get_comments_number($p = 0) { return 0; }
function comments_template($f = '', $sep = false) { }
function get_search_query($escaped = true) { return $escaped ? esc_attr((string)($_GET['keywords'] ?? '')) : (string)($_GET['keywords'] ?? ''); }
function the_search_query() { echo get_search_query(); }
function get_search_form($args = []) { $f = '<form role="search" method="get" class="search-form" action="' . esc_url(home_url('/')) . '"><input type="search" name="keywords" value="' . get_search_query() . '" placeholder="' . esc_attr__('Search') . '"><input type="hidden" name="search" value="Search"><button type="submit">' . esc_html__('Search') . '</button></form>'; if (!isset($args['echo']) || $args['echo']) echo $f; return $f; }
function dynamic_sidebar($i = 1) { return false; }
function is_active_sidebar($i) { return false; }
function the_posts_pagination($a = []) { }
function the_posts_navigation($a = []) { }
function the_post_navigation($a = []) { }
function next_posts_link($l = null, $m = 0) { }
function previous_posts_link($l = null) { }
function posts_nav_link() { }
function paginate_links($a = []) { return ''; }
function get_post_format($p = null) { return false; }
function edit_post_link($t = null, $b = '', $a = '', $id = 0, $c = '') { }
function wp_link_pages($a = []) { }
function get_edit_post_link($id = 0) { return ''; }
function get_the_password_form($p = 0) { return ''; }
function wp_attachment_is_image($id = 0) { return false; }
function get_custom_header() { return (object)['url' => '']; }
function get_header_image() { return false; }
function has_custom_logo() { return false; }
function the_custom_logo() { }
function get_custom_logo() { return ''; }
function get_theme_mod($n, $d = false) { $v = get_option('theme_mods_' . Theme::active()); return is_array($v) && array_key_exists($n, $v) ? $v[$n] : $d; }
function set_theme_mod($n, $v) { $m = get_option('theme_mods_' . Theme::active(), []); $m = is_array($m) ? $m : []; $m[$n] = $v; return update_option('theme_mods_' . Theme::active(), $m); }
function get_header_textcolor() { return ''; }
function display_header_text() { return true; }
function wp_get_attachment_image_src($id, $size = 'thumbnail') { return false; }
function has_nav_menu($loc) { return isset(Kk_Wp::$menus[$loc]); }
function has_nav_menu_items() { return true; }

/** The site's own navigation, in the markup wp_nav_menu() produces. */
function wp_nav_menu($args = [])
{
    $args = wp_parse_args($args, ['container' => 'div', 'container_class' => '', 'menu_class' => 'menu', 'menu_id' => '', 'echo' => true, 'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s</ul>', 'before' => '', 'after' => '', 'link_before' => '', 'link_after' => '']);
    $s = Settings::all();
    $items = [[__('Home'), home_url('/')], [__('Books'), home_url(Permalink::enabled() ? 'shelf' : 'index.php?p=shelf')]];
    foreach (['series' => __('Series'), 'lists' => __('Reading lists'), 'blog' => (string)($s['blog_title'] ?: __('Journal')), 'faq' => __('FAQ'), 'contact' => __('Contact')] as $p => $label) {
        if (($p === 'lists' && !$s['enable_lists']) || ($p === 'blog' && !$s['blog_enable']) || ($p === 'contact' && !$s['enable_contact'])) continue;
        $to = Permalink::toPretty(['p' => $p]);
        $items[] = [$label, home_url(Permalink::enabled() && $to ? $to[0] : 'index.php?p=' . $p)];
    }
    $li = '';
    foreach ($items as [$l, $u]) $li .= '<li class="menu-item">' . $args['before'] . '<a href="' . esc_url($u) . '">' . $args['link_before'] . esc_html($l) . $args['link_after'] . '</a>' . $args['after'] . '</li>';
    $ul = sprintf($args['items_wrap'], esc_attr($args['menu_id']), esc_attr($args['menu_class']), $li);
    $out = $args['container'] ? '<' . $args['container'] . ($args['container_class'] ? ' class="' . esc_attr($args['container_class']) . '"' : '') . '>' . $ul . '</' . $args['container'] . '>' : $ul;
    if ($args['echo']) echo $out;
    return $out;
}
function wp_page_menu($args = []) { return wp_nav_menu($args); }
function wp_list_pages($args = []) { $args = wp_parse_args($args, ['echo' => 1]); $o = wp_nav_menu(['container' => false, 'echo' => false]); if ($args['echo']) echo $o; return $o; }
function wp_get_nav_menu_items($m) { return []; }
function get_nav_menu_locations() { return []; }
function get_page_by_path($p, $o = OBJECT, $t = 'page') { return null; }
function get_pages($a = []) { return []; }
function get_posts($a = []) { return []; }
function get_categories($a = []) { return []; }
function get_terms($a = []) { return []; }
function wp_list_categories($a = []) { }
function wp_tag_cloud($a = []) { }
function get_categories_list() { return []; }

class WP_Query
{
    public array $posts = [];
    public int $post_count = 0, $found_posts = 0, $max_num_pages = 1;
    private int $i = -1;
    public function __construct($args = []) { }
    public function have_posts() { return $this->i + 1 < count($this->posts); }
    public function the_post() { $this->i++; }
    public function rewind_posts() { $this->i = -1; }
}
function get_query_var($v, $d = '') { return $_GET[$v] ?? $d; }
function wp_enqueue_comment_reply() { }
function wp_get_attachment_url($id = 0) { return ''; }

/* The bridge runs the theme. $main_content and $page_title come from the page being served. */
Kk_Wp_Theme::render((string)($main_content ?? ''), (string)($page_title ?? ''));
