<?php
/**
 * Kutubkhanah: themes, modelled on WordPress. A theme is a folder in template/ with a theme.json manifest.
 * A child theme names a parent and only holds the files it changes; everything else comes from the parent.
 *
 *  template/<slug>/theme.json      name, description, version, author, license, parent, screenshot, styles, scripts
 *  template/<slug>/functions.php   optional PHP loaded with the theme (parent first)
 *  template/<slug>/screenshot.png  shown in Appearance > Themes
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Theme
{
    private static array $manifests = [];

    private static function root(): string
    {
        global $sysconf;
        return rtrim(SB, '/') . '/' . $sysconf['template']['dir'] . '/';
    }

    public static function valid(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $slug) && is_dir(self::root() . $slug);
    }

    public static function active(): string
    {
        global $sysconf;
        return (string)$sysconf['template']['theme'];
    }

    /** @return array<string,mixed> manifest with defaults filled in */
    public static function manifest(string $slug): array
    {
        if (isset(self::$manifests[$slug])) return self::$manifests[$slug];
        $m = [];
        $f = self::root() . $slug . '/theme.json';
        if (is_file($f)) {
            $j = json_decode((string)file_get_contents($f), true);
            if (is_array($j)) $m = $j;
        }
        $type = 'native';
        $css = self::root() . $slug . '/style.css';
        if (!$m && is_file($css)) {
            $head = (string)file_get_contents($css, false, null, 0, 8192);
            if (preg_match('/^[ \t\/*#@]*Theme Name:\s*(.+)$/mi', $head, $x)) {
                $h = fn($k) => preg_match('/^[ \t\/*#@]*' . $k . ':\s*(.+)$/mi', $head, $y) ? trim($y[1]) : '';
                $m = ['name' => trim($x[1]), 'description' => $h('Description'), 'version' => $h('Version'), 'author' => trim(strip_tags($h('Author'))), 'license' => $h('License'), 'parent' => strtolower($h('Template'))];
                $type = 'wordpress';
            }
        }
        if (!$m && is_file(self::root() . $slug . '/theme.ini')) {
            $ini = @parse_ini_file(self::root() . $slug . '/theme.ini', true) ?: [];
            $t = $ini['theme'] ?? $ini;
            $m = ['name' => (string)($t['title'] ?? ''), 'description' => (string)($t['description'] ?? ''), 'version' => '', 'author' => (string)($t['author'] ?? ''), 'license' => (string)($t['license'] ?? '')];
            $type = 'omeka';
        }
        $shot = '';
        foreach ([$m['screenshot'] ?? '', 'screenshot.png', 'screenshot.jpg', 'preview.png'] as $c) {
            if ($c !== '' && preg_match('#^[A-Za-z0-9._/-]+$#', $c) && !str_contains($c, '..') && is_file(self::root() . $slug . '/' . $c)) { $shot = $c; break; }
        }
        return self::$manifests[$slug] = [
            'slug' => $slug,
            'type' => $type,
            'name' => (string)(($m['name'] ?? '') !== '' ? $m['name'] : ucwords(str_replace('-', ' ', $slug))),
            'description' => (string)($m['description'] ?? ''),
            'version' => (string)($m['version'] ?? ''),
            'author' => (string)($m['author'] ?? ''),
            'author_url' => (string)($m['author_url'] ?? ''),
            'license' => (string)($m['license'] ?? ''),
            'parent' => preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', (string)($m['parent'] ?? '')) ? (string)$m['parent'] : '',
            'screenshot' => $shot,
            'styles' => array_values(array_filter((array)($m['styles'] ?? []), 'is_string')),
            'scripts' => array_values(array_filter((array)($m['scripts'] ?? []), 'is_string')),
            'hidden' => !empty($m['hidden']),
        ];
    }

    /** Every theme the visitor can choose. @return array<string,array> */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::root() . '*', GLOB_ONLYDIR) ?: [] as $d) {
            $slug = basename($d);
            if (!self::valid($slug)) continue;
            $m = self::manifest($slug);
            if (!is_file($d . '/theme.json') && !is_file($d . '/index_template.inc.php') && $m['type'] === 'native') continue;
            if ($m['hidden']) continue;
            if ($m['parent'] === '' && $m['type'] === 'native' && !is_file($d . '/index_template.inc.php')) continue;
            if ($m['type'] === 'wordpress' && $m['parent'] === '' && !is_file($d . '/index.php')) continue;
            if ($m['parent'] !== '' && !self::valid($m['parent'])) continue;
            $out[$slug] = $m;
        }
        ksort($out);
        return $out;
    }

    /** Slugs from the theme itself up to its oldest parent. @return string[] */
    public static function chain(?string $slug = null): array
    {
        $slug = $slug ?? self::active();
        $out = [];
        while ($slug !== '' && self::valid($slug) && !in_array($slug, $out, true) && count($out) < 4) {
            $out[] = $slug;
            $slug = self::manifest($slug)['parent'];
        }
        return $out;
    }

    /** What the active theme was written for: native, wordpress or omeka (taken from its oldest parent). */
    public static function kind(): string
    {
        $c = self::chain();
        foreach ($c as $slug) if (is_file(self::root() . $slug . '/index_template.inc.php')) return 'native';
        return $c ? self::manifest(end($c))['type'] : 'native';
    }

    /** Absolute path of a theme file: the child's own, else the parent's, else the default theme's. */
    public static function file(string $rel): string
    {
        $rel = ltrim($rel, '/');
        foreach (self::chain() as $slug) {
            $p = self::root() . $slug . '/' . $rel;
            if (is_file($p)) return $p;
        }
        $kind = self::kind();
        if ($rel === 'index_template.inc.php' && $kind === 'wordpress') return __DIR__ . '/compat/wordpress-theme.php';
        if ($rel === 'index_template.inc.php' && $kind === 'omeka') return __DIR__ . '/compat/omeka-theme.php';
        if ($kind !== 'native' || self::active() !== 'default') {
            $d = self::root() . 'default/' . $rel;
            if (is_file($d) && !in_array('default', self::chain(), true)) return $d;
        }
        return self::root() . self::active() . '/' . $rel;
    }

    /** Web path (relative to the site root) of a file under assets/, taken from the first theme in the chain that has it. */
    public static function asset(string $rel): string
    {
        global $sysconf;
        $plain = explode('?', $rel, 2)[0];
        foreach (self::chain() as $slug) {
            if (is_file(self::root() . $slug . '/assets/' . $plain)) return $sysconf['template']['dir'] . '/' . $slug . '/assets/' . $rel;
        }
        if (is_file(self::root() . 'default/assets/' . $plain)) return $sysconf['template']['dir'] . '/default/assets/' . $rel;
        return $sysconf['template']['dir'] . '/' . self::active() . '/assets/' . $rel;
    }

    /** Called from index.php once the settings are loaded. */
    public static function boot(): void
    {
        global $sysconf;
        self::preview();
        $chain = self::chain();
        if (!$chain) return;
        // Template files include each other by relative name (parts/header.php): search the child first, then its parents.
        $paths = array_map(fn($s) => rtrim(self::root(), '/') . '/' . $s, $chain);
        set_include_path(implode(PATH_SEPARATOR, array_merge($paths, [get_include_path()])));
        // A child theme without its own tinfo.inc.php or style.css uses the parent's.
        foreach (array_slice(array_reverse($chain), 0, -1) as $parent) {
            $t = self::root() . $parent . '/tinfo.inc.php';
            if (!is_file(self::root() . $chain[0] . '/tinfo.inc.php') && is_file($t)) require_once $t;
        }
        if (!is_file(self::root() . $chain[0] . '/style.css')) {
            $css = self::file('style.css');
            if (is_file($css)) $sysconf['template']['css'] = $sysconf['template']['dir'] . '/' . basename(dirname($css)) . '/style.css';
        }
        $kind = self::kind();
        if ($kind !== 'native') {
            require_once __DIR__ . '/compat/wordpress.php';
            if ($kind === 'omeka') require_once __DIR__ . '/compat/omeka.php';
        }
        foreach (array_reverse($chain) as $slug) {
            $fn = self::root() . $slug . '/functions.php';
            if (is_file($fn)) {
                try { require_once $fn; } catch (\Throwable $e) { error_log('Kutubkhanah theme ' . $slug . ': ' . $e->getMessage()); }
            }
        }
    }

    /** Staff can try a theme on the public site without activating it: ?kk_theme=slug, ?kk_theme= to stop. */
    private static function preview(): void
    {
        global $sysconf;
        if (PHP_SAPI === 'cli' || empty($_SESSION['uid'])) return;
        if (isset($_GET['kk_theme'])) {
            $want = (string)$_GET['kk_theme'];
            if ($want === '') unset($_SESSION['kk_preview_theme']);
            elseif (isset(self::all()[$want])) $_SESSION['kk_preview_theme'] = $want;
        }
        $slug = (string)($_SESSION['kk_preview_theme'] ?? '');
        if ($slug !== '' && self::valid($slug) && $slug !== self::active()) {
            $sysconf['template']['theme'] = $slug;
            $sysconf['template']['css'] = $sysconf['template']['dir'] . '/' . $slug . '/style.css';
            self::$manifests = [];
            if (is_file(self::root() . $slug . '/tinfo.inc.php')) { global $sysconf; require_once self::root() . $slug . '/tinfo.inc.php'; }
            ob_start([self::class, 'previewBar']);
        }
    }

    public static function previewBar(string $html): string
    {
        $m = self::manifest(self::active());
        $bar = '<div style="position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#0f172a;color:#fff;padding:.6rem 1rem;font:14px system-ui,sans-serif;display:flex;gap:1rem;align-items:center;justify-content:center">'
            . htmlspecialchars(sprintf(__('Previewing the theme "%s". Visitors still see your active theme.'), $m['name']), ENT_QUOTES, 'UTF-8')
            . ' <a style="color:#c9a227" href="' . htmlspecialchars((string)SWB, ENT_QUOTES) . '?kk_theme=">' . __('Stop previewing') . '</a></div>';
        $pos = strripos($html, '</body>');
        return $pos === false ? $html : substr($html, 0, $pos) . $bar . substr($html, $pos);
    }

    /** Stylesheets and scripts named in theme.json (child last), for the page head. */
    public static function head(): string
    {
        global $sysconf;
        $out = '';
        foreach (array_reverse(self::chain()) as $slug) {
            foreach (self::manifest($slug)['styles'] as $f) {
                if (is_file(self::root() . $slug . '/' . $f)) $out .= '    <link rel="stylesheet" href="' . htmlspecialchars($sysconf['template']['dir'] . '/' . $slug . '/' . $f, ENT_QUOTES) . '?v=' . filemtime(self::root() . $slug . '/' . $f) . '">' . "\n";
            }
        }
        return $out;
    }

    public static function footer(): string
    {
        global $sysconf;
        $out = '';
        foreach (array_reverse(self::chain()) as $slug) {
            foreach (self::manifest($slug)['scripts'] as $f) {
                if (is_file(self::root() . $slug . '/' . $f)) $out .= '<script src="' . htmlspecialchars($sysconf['template']['dir'] . '/' . $slug . '/' . $f, ENT_QUOTES) . '?v=' . filemtime(self::root() . $slug . '/' . $f) . '"></script>' . "\n";
            }
        }
        return $out;
    }

    /** Make a theme the active one (same setting the stock System > Template screen uses). */
    public static function activate(string $slug): bool
    {
        if (!isset(self::all()[$slug])) return false;
        $db = \SLiMS\DB::getInstance();
        $st = $db->prepare("SELECT setting_value FROM setting WHERE setting_name = 'template'");
        $st->execute();
        $cur = $st->fetchColumn();
        $arr = $cur !== false ? @unserialize((string)$cur) : [];
        if (!is_array($arr)) $arr = [];
        $arr['theme'] = $slug;
        $arr['css'] = 'template/' . $slug . '/style.css';
        $val = serialize($arr);
        if ($cur !== false) $db->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = 'template'")->execute([$val]);
        else $db->prepare("INSERT INTO setting (setting_name, setting_value) VALUES ('template', ?)")->execute([$val]);
        return true;
    }
}
