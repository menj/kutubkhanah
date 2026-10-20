<?php
/**
 * Kutubkhanah: WordPress compatibility layer for plugins and themes.
 *
 * Runs plugins that stick to WordPress's public API: hooks, shortcodes, options, enqueueing, admin pages, the Settings API,
 * simple $wpdb queries and the common helpers. It is not WordPress. There are no posts, users, taxonomies or wp-admin,
 * so plugins that depend on those (WooCommerce, page builders, SEO suites) will not work. See docs/plugins.md.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Hooks;
use SLiMS\Kutubkhanah\Plugins;
use SLiMS\Kutubkhanah\Feed;
use SLiMS\Kutubkhanah\Settings;

if (!defined('ABSPATH')) {
    define('ABSPATH', rtrim(SB, '/') . '/');
    define('WPINC', 'wp-includes');
    define('WP_PLUGIN_DIR', rtrim(SB, '/') . '/plugins');
    define('WP_CONTENT_DIR', rtrim(SB, '/') . '/files');
    define('WP_DEBUG', false);
    define('WP_DEBUG_LOG', false);
    define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600); define('DAY_IN_SECONDS', 86400);
    define('WEEK_IN_SECONDS', 604800); define('MONTH_IN_SECONDS', 2592000); define('YEAR_IN_SECONDS', 31536000);
    define('OBJECT', 'OBJECT'); define('OBJECT_K', 'OBJECT_K'); define('ARRAY_A', 'ARRAY_A'); define('ARRAY_N', 'ARRAY_N');
}

/** State and helpers behind the global functions. */
class Kk_Wp
{
    public static array $styles = [], $scripts = [], $inline = [], $settings = [], $sections = [], $fields = [], $support = [], $menus = [], $localized = [];
    public static string $plugin = '';
    public static array $pluginDirs = [];
    private static bool $headDone = false, $footDone = false;

    public static function enter(string $slug, string $file): void
    {
        self::$plugin = $slug;
        self::$pluginDirs[rtrim(dirname($file), '/')] = $slug;
        self::wire();
    }

    public static function slugOf(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        foreach (self::$pluginDirs as $dir => $slug) if (str_starts_with($path, $dir)) return $slug;
        if (preg_match('#/plugins/([^/]+)/#', $path, $m)) return $m[1];
        return self::$plugin;
    }

    public static function wire(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        Hooks::add('kk_head', function () { self::printHead(); });
        Hooks::add('kk_footer', function () { self::printFoot(); });
    }

    public static function printHead(): void
    {
        if (self::$headDone) return;
        self::$headDone = true;
        Hooks::action('wp_enqueue_scripts');
        Hooks::action('wp_head');
        foreach (self::$styles as $h => $s) if ($s['on'] && $s['src']) echo '<link rel="stylesheet" id="' . esc_attr($h) . '-css" href="' . esc_url($s['src']) . ($s['ver'] ? '?ver=' . rawurlencode($s['ver']) : '') . "\">\n";
        foreach (self::$inline as [$kind, $h, $code]) if ($kind === 'style') echo '<style id="' . esc_attr($h) . "-inline-css\">$code</style>\n";
        foreach (self::$scripts as $h => $s) if ($s['on'] && $s['src'] && !$s['footer']) self::printScript($h, $s);
    }

    public static function printFoot(): void
    {
        if (self::$footDone) return;
        self::$footDone = true;
        Hooks::action('wp_footer');
        foreach (self::$scripts as $h => $s) if ($s['on'] && $s['src'] && $s['footer']) self::printScript($h, $s);
    }

    private static function printScript(string $h, array $s): void
    {
        foreach ($s['deps'] as $d) if (isset(self::$scripts[$d]) && !self::$scripts[$d]['printed'] && self::$scripts[$d]['src']) self::printScript($d, self::$scripts[$d]);
        if (self::$scripts[$h]['printed']) return;
        self::$scripts[$h]['printed'] = true;
        if (!empty(self::$localized[$h])) foreach (self::$localized[$h] as [$name, $data]) echo '<script>var ' . preg_replace('/[^A-Za-z0-9_]/', '', $name) . ' = ' . wp_json_encode($data) . ";</script>\n";
        foreach (self::$inline as [$kind, $hh, $code, $pos]) if ($kind === 'script' && $hh === $h && $pos === 'before') echo "<script>$code</script>\n";
        echo '<script id="' . esc_attr($h) . '-js" src="' . esc_url($s['src']) . ($s['ver'] ? '?ver=' . rawurlencode($s['ver']) : '') . "\"></script>\n";
        foreach (self::$inline as [$kind, $hh, $code, $pos]) if ($kind === 'script' && $hh === $h && $pos === 'after') echo "<script>$code</script>\n";
    }

    public static function nonceKey(): string
    {
        if (empty($_SESSION['kk_wp_nonce'])) $_SESSION['kk_wp_nonce'] = bin2hex(random_bytes(16));
        return (string)$_SESSION['kk_wp_nonce'];
    }
}

/** Minimal $wpdb on top of the portable database layer. */
class Kk_Wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0, $num_rows = 0, $rows_affected = 0;
    public string $last_error = '', $last_query = '', $charset = 'utf8mb4', $collate = 'utf8mb4_unicode_ci', $base_prefix = 'wp_';
    public $last_result = [];
    public string $posts = 'wp_posts', $postmeta = 'wp_postmeta', $users = 'wp_users', $usermeta = 'wp_usermeta', $options = 'wp_options', $terms = 'wp_terms', $comments = 'wp_comments';
    private bool $quiet = false;

    private function pdo(): \PDO { return \SLiMS\DB::getInstance(); }

    public function prepare(string $query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        $i = 0;
        $query = str_replace("'%s'", '%s', str_replace('"%s"', '%s', $query));
        return (string)preg_replace_callback('/%(?:(\d+)\$)?([sdfF%])/', function ($m) use (&$i, $args) {
            if ($m[2] === '%') return '%';
            $v = $args[$m[1] !== '' ? (int)$m[1] - 1 : $i++] ?? null;
            if ($m[2] === 'd') return (string)(int)$v;
            if ($m[2] === 'f' || $m[2] === 'F') return (string)(float)$v;
            return $v === null ? 'NULL' : $this->pdo()->quote((string)$v);
        }, $query);
    }

    public function esc_like(string $s): string { return addcslashes($s, '_%\\'); }
    public function _escape($s) { return is_array($s) ? array_map([$this, '_escape'], $s) : substr($this->pdo()->quote((string)$s), 1, -1); }
    public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function suppress_errors($b = true) { $o = $this->quiet; $this->quiet = (bool)$b; return $o; }
    public function show_errors($b = true) { return true; }
    public function hide_errors() { return true; }
    public function flush() { $this->last_result = []; $this->last_error = ''; }

    private function run(string $sql, array $args = [])
    {
        $this->last_query = $sql;
        $this->last_error = '';
        try {
            $st = $this->pdo()->prepare($sql);
            if ($st === false) { $this->last_error = 'prepare failed'; return false; }
            $st->execute($args);
            return $st;
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            if (!$this->quiet) error_log('Kutubkhanah wpdb: ' . $e->getMessage() . ' in ' . $sql);
            return false;
        }
    }

    public function query(string $sql)
    {
        $st = $this->run($sql);
        if ($st === false) return false;
        $this->rows_affected = $st->rowCount();
        $this->insert_id = (int)$this->pdo()->lastInsertId();
        return preg_match('/^\s*(select|show|describe|explain)/i', $sql) ? $this->num_rows = count($st->fetchAll()) : $this->rows_affected;
    }

    public function get_results($sql = null, $output = OBJECT)
    {
        $st = $this->run((string)$sql);
        if ($st === false) return null;
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        $this->num_rows = count($rows);
        $this->last_result = $rows;
        if ($output === ARRAY_A) return $rows;
        if ($output === ARRAY_N) return array_map('array_values', $rows);
        $obj = array_map(fn($r) => (object)$r, $rows);
        if ($output === OBJECT_K) { $k = []; foreach ($rows as $i => $r) $k[(string)reset($r)] = $obj[$i]; return $k; }
        return $obj;
    }

    public function get_row($sql = null, $output = OBJECT, $y = 0)
    {
        $r = $this->get_results($sql, $output);
        return $r[$y] ?? null;
    }

    public function get_var($sql = null, $x = 0, $y = 0)
    {
        $r = $this->get_results($sql, ARRAY_N);
        return $r[$y][$x] ?? null;
    }

    public function get_col($sql = null, $x = 0): array
    {
        $r = $this->get_results($sql, ARRAY_N);
        return $r ? array_map(fn($row) => $row[$x] ?? null, $r) : [];
    }

    private function write(string $verb, string $table, array $data)
    {
        $cols = implode(', ', array_map(fn($c) => '"' . str_replace('"', '', $c) . '"', array_keys($data)));
        $st = $this->run($verb . ' INTO ' . $table . ' (' . str_replace('"', '`', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_map(fn($v) => is_scalar($v) || $v === null ? $v : serialize($v), array_values($data)));
        if ($st === false) return false;
        $this->insert_id = (int)$this->pdo()->lastInsertId();
        return $this->rows_affected = $st->rowCount();
    }

    public function insert($table, $data, $format = null) { return $this->write('INSERT', $table, $data); }
    public function replace($table, $data, $format = null) { return $this->write('REPLACE', $table, $data); }

    private function where(array $where): array
    {
        $parts = [];
        $vals = [];
        foreach ($where as $c => $v) { $parts[] = '`' . str_replace('`', '', $c) . '` = ?'; $vals[] = $v; }
        return [$parts ? ' WHERE ' . implode(' AND ', $parts) : '', $vals];
    }

    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        $set = [];
        $vals = [];
        foreach ($data as $c => $v) { $set[] = '`' . str_replace('`', '', $c) . '` = ?'; $vals[] = $v; }
        [$w, $wv] = $this->where($where);
        $st = $this->run('UPDATE ' . $table . ' SET ' . implode(', ', $set) . $w, array_merge($vals, $wv));
        return $st === false ? false : ($this->rows_affected = $st->rowCount());
    }

    public function delete($table, $where, $where_format = null)
    {
        [$w, $wv] = $this->where($where);
        $st = $this->run('DELETE FROM ' . $table . $w, $wv);
        return $st === false ? false : ($this->rows_affected = $st->rowCount());
    }
}

/** WP_Error */
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public array $errors = [];
        public array $error_data = [];
        public function __construct($code = '', $message = '', $data = '') { if ($code !== '') { $this->errors[$code][] = $message; if ($data !== '') $this->error_data[$code] = $data; } }
        public function get_error_code() { return array_key_first($this->errors) ?? ''; }
        public function get_error_message($code = '') { $code = $code ?: $this->get_error_code(); return $this->errors[$code][0] ?? ''; }
        public function get_error_messages($code = '') { return $code ? ($this->errors[$code] ?? []) : array_merge(...array_values($this->errors ?: [[]])); }
        public function add($code, $message, $data = '') { $this->errors[$code][] = $message; }
        public function has_errors() { return (bool)$this->errors; }
    }
}

$GLOBALS['wpdb'] = $GLOBALS['wpdb'] ?? new Kk_Wpdb();
$GLOBALS['wp_version'] = '6.5';

/* ---------------- Hooks ---------------- */
function add_action($tag, $cb, $priority = 10, $args = 1) { return Hooks::add((string)$tag, $cb, (int)$priority, (int)$args); }
function add_filter($tag, $cb, $priority = 10, $args = 1) { return Hooks::add((string)$tag, $cb, (int)$priority, (int)$args); }
function remove_action($tag, $cb, $priority = 10) { return Hooks::remove((string)$tag, $cb, (int)$priority); }
function remove_filter($tag, $cb, $priority = 10) { return Hooks::remove((string)$tag, $cb, (int)$priority); }
function do_action($tag, ...$args) { Hooks::action((string)$tag, ...$args); }
function do_action_ref_array($tag, $args) { Hooks::action((string)$tag, ...array_values((array)$args)); }
function apply_filters($tag, $value, ...$args) { return Hooks::filter((string)$tag, $value, ...$args); }
function has_action($tag, $cb = false) { return Hooks::has((string)$tag, $cb ?: null); }
function has_filter($tag, $cb = false) { return Hooks::has((string)$tag, $cb ?: null); }
function did_action($tag) { return Hooks::did((string)$tag); }
function doing_action($tag = null) { return false; }
function register_activation_hook($file, $cb) { Hooks::add('activate_' . Kk_Wp::slugOf($file), $cb, 10, 0); }
function register_deactivation_hook($file, $cb) { Hooks::add('deactivate_' . Kk_Wp::slugOf($file), $cb, 10, 0); }
function register_uninstall_hook($file, $cb) { }

/* ---------------- Shortcodes ---------------- */
function add_shortcode($tag, $cb) { Hooks::addShortcode((string)$tag, $cb); }
function remove_shortcode($tag) { Hooks::removeShortcode((string)$tag); }
function shortcode_exists($tag) { return Hooks::shortcodeExists((string)$tag); }
function do_shortcode($content, $ignore_html = false) { return Hooks::shortcodes((string)$content); }
function shortcode_atts($pairs, $atts, $shortcode = '') { return Hooks::atts((array)$pairs, $atts); }
function has_shortcode($content, $tag) { return (bool)preg_match('/\[' . preg_quote((string)$tag, '/') . '[\s\]\/]/', (string)$content); }
function shortcode_parse_atts($text) { return Hooks::parseAtts((string)$text); }

/* ---------------- Options (stored in kk_option) ---------------- */
function kk_wp_option_db(): PDO { return \SLiMS\DB::getInstance(); }
function get_option($name, $default = false)
{
    static $cache = [];
    $name = (string)$name;
    $core = ['blogname' => fn() => get_bloginfo('name'), 'blogdescription' => fn() => get_bloginfo('description'), 'siteurl' => fn() => untrailingslashit(home_url()), 'home' => fn() => untrailingslashit(home_url()),
        'admin_email' => fn() => get_bloginfo('admin_email'), 'date_format' => fn() => 'F j, Y', 'time_format' => fn() => 'g:i a', 'posts_per_page' => fn() => 10, 'show_on_front' => fn() => 'posts',
        'page_on_front' => fn() => 0, 'page_for_posts' => fn() => 0, 'WPLANG' => fn() => get_locale(), 'timezone_string' => fn() => date_default_timezone_get(), 'blog_charset' => fn() => 'UTF-8',
        'stylesheet' => fn() => \SLiMS\Kutubkhanah\Theme::active(), 'template' => fn() => \SLiMS\Kutubkhanah\Theme::active(), 'active_plugins' => fn() => \SLiMS\Kutubkhanah\Plugins::active()];
    if (isset($core[$name])) return $core[$name]();
    if (array_key_exists($name, $cache)) return apply_filters('option_' . $name, $cache[$name]);
    try {
        $st = kk_wp_option_db()->prepare('SELECT value FROM kk_option WHERE name = ?');
        $st->execute([$name]);
        $v = $st->fetchColumn();
    } catch (\Throwable $e) { \SLiMS\Kutubkhanah\Schema::ensure($GLOBALS['dbs']); $v = false; }
    if ($v === false) return $default;
    $u = @unserialize((string)$v);
    return $cache[$name] = ($u === false && $v !== 'b:0;') ? $v : $u;
}
function update_option($name, $value, $autoload = null)
{
    $name = (string)$name;
    $val = is_scalar($value) && !is_bool($value) ? (string)$value : serialize($value);
    if (is_scalar($value) && !is_bool($value) && @unserialize($val) !== false) $val = serialize($value);
    $db = kk_wp_option_db();
    try {
        $st = $db->prepare('UPDATE kk_option SET value = ? WHERE name = ?');
        $st->execute([$val, $name]);
        if (!$st->rowCount()) {
            $c = $db->prepare('SELECT 1 FROM kk_option WHERE name = ?');
            $c->execute([$name]);
            if (!$c->fetchColumn()) $db->prepare('INSERT INTO kk_option (name, value) VALUES (?, ?)')->execute([$name, $val]);
        }
    } catch (\Throwable $e) { \SLiMS\Kutubkhanah\Schema::ensure($GLOBALS['dbs']); return false; }
    return true;
}
function add_option($name, $value = '', $deprecated = '', $autoload = 'yes') { return get_option($name, null) === null || get_option($name, false) === false ? update_option($name, $value) : false; }
function delete_option($name) { try { kk_wp_option_db()->prepare('DELETE FROM kk_option WHERE name = ?')->execute([(string)$name]); } catch (\Throwable $e) {} return true; }
function get_transient($k) { $v = get_option('_transient_' . $k, false); $t = get_option('_transient_timeout_' . $k, 0); return ($v !== false && (!$t || $t > time())) ? $v : false; }
function set_transient($k, $v, $exp = 0) { update_option('_transient_' . $k, $v); update_option('_transient_timeout_' . $k, $exp ? time() + $exp : 0); return true; }
function delete_transient($k) { delete_option('_transient_' . $k); delete_option('_transient_timeout_' . $k); return true; }
function get_site_option($n, $d = false) { return get_option($n, $d); }
function update_site_option($n, $v) { return update_option($n, $v); }

/* ---------------- URLs and site info ---------------- */
function home_url($path = '', $scheme = null) { return rtrim(Feed::baseUrl(), '/') . '/' . ltrim((string)$path, '/'); }
function site_url($path = '', $scheme = null) { return home_url($path); }
function admin_url($path = '', $scheme = 'admin') { return rtrim(Feed::baseUrl(), '/') . '/admin/modules/kutubkhanah/plugin-page.php?' . (preg_match('/[?&]page=([^&]+)/', (string)$path, $m) ? 'page=' . $m[1] : ''); }
function includes_url($path = '') { return home_url('wp-includes/' . ltrim((string)$path, '/')); }
function content_url($path = '') { return home_url('files/' . ltrim((string)$path, '/')); }
function plugin_dir_path($file) { return rtrim(dirname((string)$file), '/') . '/'; }
function plugin_dir_url($file) { return home_url('plugins/' . Kk_Wp::slugOf((string)$file) . '/'); }
function plugins_url($path = '', $plugin = '') { return $plugin ? plugin_dir_url($plugin) . ltrim((string)$path, '/') : home_url('plugins/' . ltrim((string)$path, '/')); }
function plugin_basename($file) { $s = Kk_Wp::slugOf((string)$file); return $s . '/' . basename((string)$file); }
function trailingslashit($s) { return rtrim((string)$s, '/\\') . '/'; }
function untrailingslashit($s) { return rtrim((string)$s, '/\\'); }
function get_bloginfo($show = '', $filter = 'raw')
{
    global $sysconf;
    switch ($show) {
        case 'name': return (string)($sysconf['library_name'] ?? '');
        case 'description': return (string)($sysconf['library_subname'] ?? '');
        case 'url': case 'wpurl': case 'home': case 'siteurl': return untrailingslashit(home_url());
        case 'admin_email': return (string)Settings::get('notify_email');
        case 'version': return $GLOBALS['wp_version'];
        case 'language': return str_replace('_', '-', (string)($sysconf['default_lang'] ?? 'en_US'));
        case 'charset': return 'UTF-8';
        case 'html_type': return 'text/html';
        case 'stylesheet_url': return get_stylesheet_uri();
        case 'template_url': case 'template_directory': return get_template_directory_uri();
        case 'rss2_url': return home_url('feed.xml');
        case 'text_direction': return 'ltr';
        default: return (string)($sysconf['library_name'] ?? '');
    }
}
function bloginfo($show = '') { echo esc_html(get_bloginfo($show)); }
function get_locale() { global $sysconf; return (string)($sysconf['default_lang'] ?? 'en_US'); }
function is_admin() { return str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/'); }
function is_ssl() { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }
function is_multisite() { return false; }
function wp_get_theme($slug = '') { $m = \SLiMS\Kutubkhanah\Theme::manifest($slug ?: \SLiMS\Kutubkhanah\Theme::active()); return new class($m) { public function __construct(private array $m) {} public function get($k) { return $this->m[strtolower($k)] ?? ''; } public function __get($k) { return $this->get($k); } }; }

/* ---------------- Escaping and sanitizing ---------------- */
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_js($s) { return str_replace(["\r", "\n"], ['', '\\n'], addslashes((string)$s)); }
function esc_url($u, $protocols = null, $context = 'display') { $u = trim((string)$u); return preg_match('#^(https?:|mailto:|tel:|/|\#|\?|[A-Za-z0-9_.-]+/|[A-Za-z0-9_.-]+$)#i', $u) && !preg_match('#^\s*(javascript|data):#i', $u) ? htmlspecialchars($u, ENT_QUOTES, 'UTF-8') : ''; }
function esc_url_raw($u) { return html_entity_decode(esc_url($u), ENT_QUOTES, 'UTF-8'); }
function esc_sql($s) { return $GLOBALS['wpdb']->_escape($s); }
function sanitize_text_field($s) { return trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$s))); }
function sanitize_textarea_field($s) { return trim(strip_tags((string)$s)); }
function sanitize_key($s) { return strtolower((string)preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$s)); }
function sanitize_title($s, $fallback = '', $ctx = 'save') { $t = \SLiMS\Kutubkhanah\Permalink::slugify((string)$s); return $t !== '' ? $t : $fallback; }
function sanitize_title_with_dashes($s) { return sanitize_title($s); }
function sanitize_file_name($s) { return preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$s); }
function sanitize_email($s) { return filter_var((string)$s, FILTER_VALIDATE_EMAIL) ?: ''; }
function sanitize_hex_color($c) { return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string)$c) ? $c : null; }
function is_email($s) { return (bool)filter_var((string)$s, FILTER_VALIDATE_EMAIL); }
function wp_kses_post($s) { return \SLiMS\Kutubkhanah\Blog\Text::clean((string)$s); }
function wp_kses($s, $allowed = [], $protocols = []) { return strip_tags((string)$s, array_map(fn($t) => "<$t>", array_keys((array)$allowed))); }
function wp_strip_all_tags($s, $remove_breaks = false) { $s = strip_tags((string)preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string)$s)); return $remove_breaks ? trim((string)preg_replace('/[\r\n\t ]+/', ' ', $s)) : trim($s); }
function absint($n) { return abs((int)$n); }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : (is_string($v) ? stripslashes($v) : $v); }
function wp_slash($v) { return is_array($v) ? array_map('wp_slash', $v) : (is_string($v) ? addslashes($v) : $v); }
function wp_parse_args($args, $defaults = []) { if (is_string($args)) parse_str($args, $args); return array_merge((array)$defaults, (array)$args); }
function wp_json_encode($d, $flags = 0, $depth = 512) { return json_encode($d, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, $depth); }
function wp_generate_password($len = 12) { return substr(bin2hex(random_bytes($len)), 0, $len); }
function wp_rand($a = 0, $b = 0) { return random_int($a ?: 0, $b ?: mt_getrandmax()); }
function wp_list_pluck($list, $field) { return array_map(fn($i) => is_object($i) ? $i->$field : $i[$field], (array)$list); }
function wp_date($format, $ts = null) { return date($format, $ts ?? time()); }
function current_time($type, $gmt = 0) { return $type === 'timestamp' || $type === 'U' ? time() : date($type === 'mysql' ? 'Y-m-d H:i:s' : $type); }
function human_time_diff($from, $to = 0) { $d = abs(($to ?: time()) - $from); return $d < 3600 ? round($d / 60) . ' mins' : ($d < 86400 ? round($d / 3600) . ' hours' : round($d / 86400) . ' days'); }
function maybe_serialize($v) { return is_array($v) || is_object($v) ? serialize($v) : $v; }
function maybe_unserialize($v) { return is_string($v) && ($u = @unserialize($v)) !== false ? $u : $v; }
function is_wp_error($t) { return $t instanceof WP_Error; }
function wp_die($msg = '', $title = '', $args = []) { while (ob_get_level() > 0) ob_end_clean(); http_response_code(500); echo is_string($msg) ? '<p>' . esc_html($msg) . '</p>' : 'Error'; exit; }
function wp_redirect($loc, $status = 302) { header('Location: ' . $loc, true, $status); return true; }
function wp_safe_redirect($loc, $status = 302) { return wp_redirect($loc, $status); }
function status_header($code) { http_response_code((int)$code); }
function wp_send_json($r, $status = null) { while (ob_get_level() > 0) ob_end_clean(); header('Content-Type: application/json'); echo wp_json_encode($r); exit; }
function wp_send_json_success($d = null) { wp_send_json(['success' => true, 'data' => $d]); }
function wp_send_json_error($d = null) { wp_send_json(['success' => false, 'data' => $d]); }

/* ---------------- Translation (no-op domains) ---------------- */
if (!function_exists('_e')) { function _e($s, $d = 'default') { echo esc_html(__($s)); } }
function esc_html__($s, $d = 'default') { return esc_html(__($s)); }
function esc_attr__($s, $d = 'default') { return esc_attr(__($s)); }
function esc_html_e($s, $d = 'default') { echo esc_html(__($s)); }
function esc_attr_e($s, $d = 'default') { echo esc_attr(__($s)); }
function _x($s, $ctx, $d = 'default') { return __($s); }
function _n($single, $plural, $n, $d = 'default') { return __($n == 1 ? $single : $plural); }
function _nx($single, $plural, $n, $ctx, $d = 'default') { return _n($single, $plural, $n); }
function number_format_i18n($n, $dec = 0) { return number_format((float)$n, (int)$dec); }
function load_plugin_textdomain($d, $a = false, $p = false) { return true; }
function load_theme_textdomain($d, $p = false) { return true; }
function load_textdomain($d, $f) { return true; }

/* ---------------- Users and capabilities ---------------- */
function is_user_logged_in() { return !empty($_SESSION['uid']); }
function current_user_can($cap, ...$args) { return !empty($_SESSION['uid']) && (!function_exists('utility::havePrivilege') ? true : true); }
function get_current_user_id() { return (int)($_SESSION['uid'] ?? 0); }
function wp_get_current_user() { return (object)['ID' => (int)($_SESSION['uid'] ?? 0), 'user_login' => (string)($_SESSION['uname'] ?? ''), 'display_name' => (string)($_SESSION['realname'] ?? ''), 'roles' => !empty($_SESSION['uid']) ? ['administrator'] : [], 'exists' => fn() => !empty($_SESSION['uid'])]; }
function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) { $f = '<input type="hidden" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr(wp_create_nonce($action)) . '">'; if ($echo) echo $f; return $f; }
function wp_create_nonce($action = -1) { return substr(hash_hmac('sha256', (string)$action, Kk_Wp::nonceKey()), 0, 10); }
function wp_verify_nonce($nonce, $action = -1) { return hash_equals(wp_create_nonce($action), (string)$nonce) ? 1 : false; }
function check_admin_referer($action = -1, $arg = '_wpnonce') { if (!wp_verify_nonce($_REQUEST[$arg] ?? '', $action)) wp_die('The link you followed has expired.'); return 1; }
function check_ajax_referer($action = -1, $arg = false, $die = true) { return wp_verify_nonce($_REQUEST[$arg ?: '_wpnonce'] ?? '', $action); }
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') { return $url . (str_contains($url, '?') ? '&' : '?') . $name . '=' . wp_create_nonce($action); }

/* ---------------- Assets ---------------- */
function wp_register_style($h, $src, $deps = [], $ver = false, $media = 'all') { Kk_Wp::$styles[$h] = ['src' => $src, 'deps' => (array)$deps, 'ver' => $ver, 'on' => Kk_Wp::$styles[$h]['on'] ?? false]; return true; }
function wp_enqueue_style($h, $src = '', $deps = [], $ver = false, $media = 'all') { if ($src !== '' || !isset(Kk_Wp::$styles[$h])) wp_register_style($h, $src, $deps, $ver); Kk_Wp::$styles[$h]['on'] = true; foreach (Kk_Wp::$styles[$h]['deps'] as $d) if (isset(Kk_Wp::$styles[$d])) Kk_Wp::$styles[$d]['on'] = true; }
function wp_register_script($h, $src, $deps = [], $ver = false, $footer = false) { Kk_Wp::$scripts[$h] = ['src' => $src, 'deps' => (array)$deps, 'ver' => $ver, 'footer' => is_array($footer) ? !empty($footer['in_footer']) : (bool)$footer, 'on' => Kk_Wp::$scripts[$h]['on'] ?? false, 'printed' => false]; return true; }
function wp_enqueue_script($h, $src = '', $deps = [], $ver = false, $footer = false) { if ($src !== '' || !isset(Kk_Wp::$scripts[$h])) wp_register_script($h, $src, $deps, $ver, $footer); Kk_Wp::$scripts[$h]['on'] = true; }
function wp_dequeue_style($h) { if (isset(Kk_Wp::$styles[$h])) Kk_Wp::$styles[$h]['on'] = false; }
function wp_dequeue_script($h) { if (isset(Kk_Wp::$scripts[$h])) Kk_Wp::$scripts[$h]['on'] = false; }
function wp_deregister_style($h) { unset(Kk_Wp::$styles[$h]); }
function wp_deregister_script($h) { unset(Kk_Wp::$scripts[$h]); }
function wp_localize_script($h, $name, $data) { Kk_Wp::$localized[$h][] = [$name, $data]; return true; }
function wp_add_inline_script($h, $code, $pos = 'after') { Kk_Wp::$inline[] = ['script', $h, $code, $pos]; return true; }
function wp_add_inline_style($h, $code) { Kk_Wp::$inline[] = ['style', $h, $code, '']; return true; }
function wp_script_is($h, $list = 'enqueued') { return !empty(Kk_Wp::$scripts[$h]['on']); }
function wp_style_is($h, $list = 'enqueued') { return !empty(Kk_Wp::$styles[$h]['on']); }
function wp_head() { Kk_Wp::printHead(); }
function wp_footer() { Kk_Wp::printFoot(); }

/* ---------------- Admin pages and the Settings API ---------------- */
function add_menu_page($page_title, $menu_title, $cap, $slug, $cb = '', $icon = '', $pos = null) { if (is_callable($cb)) Plugins::addPage((string)$slug, (string)$menu_title ?: (string)$page_title, $cb); return $slug; }
function add_submenu_page($parent, $page_title, $menu_title, $cap, $slug, $cb = '', $pos = null) { if (is_callable($cb)) Plugins::addPage((string)$slug, (string)$menu_title ?: (string)$page_title, $cb); return $slug; }
function add_options_page($page_title, $menu_title, $cap, $slug, $cb = '', $pos = null) { return add_submenu_page('options-general.php', $page_title, $menu_title, $cap, $slug, $cb); }
function add_management_page($page_title, $menu_title, $cap, $slug, $cb = '', $pos = null) { return add_submenu_page('tools.php', $page_title, $menu_title, $cap, $slug, $cb); }
function add_theme_page($page_title, $menu_title, $cap, $slug, $cb = '', $pos = null) { return add_submenu_page('themes.php', $page_title, $menu_title, $cap, $slug, $cb); }
function register_setting($group, $name, $args = []) { Kk_Wp::$settings[$group][$name] = is_array($args) ? $args : ['sanitize_callback' => $args]; }
function add_settings_section($id, $title, $cb, $page) { Kk_Wp::$sections[$page][$id] = [$title, $cb]; }
function add_settings_field($id, $title, $cb, $page, $section = 'default', $args = []) { Kk_Wp::$fields[$page][$section][$id] = [$title, $cb, $args]; }
function settings_fields($group) { echo '<input type="hidden" name="option_page" value="' . esc_attr($group) . '">'; wp_nonce_field($group . '-options'); }
function do_settings_sections($page)
{
    foreach (Kk_Wp::$sections[$page] ?? [] as $id => [$title, $cb]) {
        if ($title) echo '<h2>' . esc_html($title) . '</h2>';
        if (is_callable($cb)) $cb(['id' => $id, 'title' => $title]);
        if (!empty(Kk_Wp::$fields[$page][$id])) {
            echo '<table class="form-table" role="presentation">';
            foreach (Kk_Wp::$fields[$page][$id] as $fid => [$ft, $fcb, $fargs]) { echo '<tr><th scope="row">' . $ft . '</th><td>'; if (is_callable($fcb)) $fcb($fargs); echo '</td></tr>'; }
            echo '</table>';
        }
    }
}
function submit_button($text = 'Save Changes', $type = 'primary', $name = 'submit', $wrap = true, $attrs = null) { echo ($wrap ? '<p class="submit">' : '') . '<button type="submit" name="' . esc_attr($name) . '" class="kk-btn kk-btn--primary">' . esc_html($text) . '</button>' . ($wrap ? '</p>' : ''); }
function settings_errors($setting = '', $sanitize = false, $hide_on_update = false) { }
function add_settings_error($setting, $code, $message, $type = 'error') { echo '<div class="kk-note">' . esc_html($message) . '</div>'; }
function get_admin_page_title() { return ''; }
function screen_icon() { }

/* Save Settings-API forms posted back to a plugin page. */
function kk_wp_handle_settings_post(): void
{
    $group = (string)($_POST['option_page'] ?? '');
    if ($group === '' || !isset(Kk_Wp::$settings[$group]) || !wp_verify_nonce($_POST['_wpnonce'] ?? '', $group . '-options')) return;
    foreach (Kk_Wp::$settings[$group] as $name => $args) {
        $v = wp_unslash($_POST[$name] ?? '');
        $cb = $args['sanitize_callback'] ?? null;
        if (is_callable($cb)) $v = $cb($v);
        update_option($name, $v);
    }
}

/* ---------------- Support flags themes and plugins call ---------------- */
function add_theme_support($f, ...$a) { Kk_Wp::$support[$f] = $a ?: true; }
function current_theme_supports($f) { return isset(Kk_Wp::$support[$f]); }
function remove_theme_support($f) { unset(Kk_Wp::$support[$f]); return true; }
function register_nav_menus($m = []) { Kk_Wp::$menus = array_merge(Kk_Wp::$menus, (array)$m); }
function register_nav_menu($l, $d) { Kk_Wp::$menus[$l] = $d; }
function register_sidebar($a = []) { return 'sidebar'; }
function register_widget($c) { }
function register_post_type($t, $a = []) { return null; }
function register_taxonomy($t, $o, $a = []) { return null; }
function register_block_type($t, $a = []) { return null; }
function add_image_size($n, $w = 0, $h = 0, $c = false) { }
function set_post_thumbnail_size($w = 0, $h = 0, $c = false) { }
function add_editor_style($s = '') { }
function flush_rewrite_rules($h = true) { }
function wp_enqueue_media() { }
function dbDelta($sql)
{
    foreach ((array)$sql as $q) {
        try { \SLiMS\DB::getInstance()->exec(trim((string)$q)); } catch (\Throwable $e) { error_log('Kutubkhanah dbDelta: ' . $e->getMessage()); }
    }
    return [];
}
function wp_remote_get($url, $args = []) { $b = @file_get_contents((string)$url, false, stream_context_create(['http' => ['timeout' => (int)($args['timeout'] ?? 10), 'ignore_errors' => true]])); return $b === false ? new WP_Error('http_request_failed', 'Request failed') : ['body' => $b, 'response' => ['code' => 200], 'headers' => []]; }
function wp_remote_retrieve_body($r) { return is_array($r) ? (string)($r['body'] ?? '') : ''; }
function wp_remote_retrieve_response_code($r) { return is_array($r) ? (int)($r['response']['code'] ?? 0) : 0; }
function wp_schedule_event($t, $r, $hook, $args = []) { return true; }
function wp_next_scheduled($hook, $args = []) { return false; }
function wp_clear_scheduled_hook($hook, $args = []) { return 0; }
function wp_schedule_single_event($t, $hook, $args = []) { return true; }
