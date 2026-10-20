<?php
/**
 * Kutubkhanah: plugins. Three kinds live side by side in plugins/:
 *
 *  native     plugins/<slug>/plugin.json   (+ main PHP file) written for Kutubkhanah, using Hooks
 *  wordpress  plugins/<slug>/<file>.php with a "Plugin Name:" header; runs on the WordPress compatibility layer
 *  omeka      plugins/<slug>/plugin.ini     Omeka Classic plugin; runs on the Omeka compatibility layer
 *
 * Plugins that end in ".plugin.php" belong to the base system's own plugin screen and are left alone.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Plugins
{
    private static bool $booted = false;
    private static array $pages = [];
    private static array $errors = [];
    public static string $loading = '';

    public static function dir(): string { return rtrim(SB, '/') . '/plugins/'; }

    public static function validSlug(string $s): bool { return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,60}$/', $s) && !str_contains($s, '..'); }

    /** @return array<string,array> slug => info */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::dir() . '*', GLOB_ONLYDIR) ?: [] as $d) {
            $slug = basename($d);
            if (!self::validSlug($slug)) continue;
            $info = self::inspect($slug, $d);
            if ($info) $out[$slug] = $info;
        }
        ksort($out);
        return $out;
    }

    private static function inspect(string $slug, string $d): ?array
    {
        $base = ['slug' => $slug, 'name' => ucwords(str_replace(['-', '_'], ' ', $slug)), 'description' => '', 'version' => '', 'author' => '', 'license' => '', 'url' => '', 'main' => '', 'type' => ''];
        if (is_file($d . '/plugin.json')) {
            $j = json_decode((string)file_get_contents($d . '/plugin.json'), true);
            if (!is_array($j)) return null;
            $main = (string)($j['main'] ?? $slug . '.php');
            if (!preg_match('#^[A-Za-z0-9._/-]+$#', $main) || str_contains($main, '..')) return null;
            return array_merge($base, ['type' => 'native', 'main' => $main], array_intersect_key($j, array_flip(['name', 'description', 'version', 'author', 'license', 'url'])));
        }
        if (is_file($d . '/plugin.ini')) {
            $ini = @parse_ini_file($d . '/plugin.ini') ?: [];
            $camel = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));
            return array_merge($base, ['type' => 'omeka', 'main' => $camel . 'Plugin.php', 'name' => (string)($ini['name'] ?? $ini['title'] ?? $base['name']),
                'description' => (string)($ini['description'] ?? ''), 'version' => (string)($ini['version'] ?? ''), 'author' => (string)($ini['author'] ?? ''), 'license' => (string)($ini['license'] ?? ''), 'url' => (string)($ini['link'] ?? '')]);
        }
        foreach (glob($d . '/*.php') ?: [] as $f) {
            if (str_ends_with($f, '.plugin.php')) return null;
            $head = (string)file_get_contents($f, false, null, 0, 8192);
            if (preg_match('/^[ \t\/*#@]*Plugin Name:\s*(.+)$/mi', $head, $m)) {
                $h = fn($k) => preg_match('/^[ \t\/*#@]*' . $k . ':\s*(.+)$/mi', $head, $x) ? trim($x[1]) : '';
                return array_merge($base, ['type' => 'wordpress', 'main' => basename($f), 'name' => trim($m[1]), 'description' => $h('Description'), 'version' => $h('Version'),
                    'author' => trim(strip_tags($h('Author'))), 'license' => $h('License'), 'url' => $h('Plugin URI')]);
            }
        }
        return null;
    }

    /** @return string[] */
    public static function active(): array
    {
        try {
            $st = \SLiMS\DB::getInstance()->prepare("SELECT setting_value FROM setting WHERE setting_name = 'kk_plugins'");
            $st->execute();
            $v = $st->fetchColumn();
            $a = $v !== false ? @unserialize((string)$v) : [];
            return is_array($a) ? array_values(array_filter($a['active'] ?? [], 'is_string')) : [];
        } catch (\Throwable $e) { return []; }
    }

    private static function saveActive(array $slugs): void
    {
        $db = \SLiMS\DB::getInstance();
        $val = serialize(['active' => array_values(array_unique($slugs))]);
        $st = $db->prepare("SELECT 1 FROM setting WHERE setting_name = 'kk_plugins'");
        $st->execute();
        if ($st->fetchColumn()) $db->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = 'kk_plugins'")->execute([$val]);
        else $db->prepare("INSERT INTO setting (setting_name, setting_value) VALUES ('kk_plugins', ?)")->execute([$val]);
    }

    private static function layer(string $type): void
    {
        if ($type === 'wordpress' || $type === 'omeka') require_once __DIR__ . '/compat/wordpress.php';
        if ($type === 'omeka') require_once __DIR__ . '/compat/omeka.php';
    }

    /** Load a plugin's main file. Returns an error message, or '' when it loaded. */
    private static function load(array $p): string
    {
        $dir = self::dir() . $p['slug'] . '/';
        $file = $dir . $p['main'];
        if (!is_file($file)) return sprintf(__('Main file %s is missing.'), $p['main']);
        self::layer($p['type']);
        self::$loading = $p['slug'];
        try {
            if ($p['type'] === 'omeka') {
                \Kk_Omeka::loadPlugin($p['slug'], $p['name'], $dir, $file);
            } else {
                if ($p['type'] === 'wordpress') \Kk_Wp::enter($p['slug'], $file);
                require_once $file;
            }
        } catch (\Throwable $e) {
            return $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally { self::$loading = ''; }
        return '';
    }

    /** Load every active plugin once per request. */
    public static function boot(): void
    {
        if (self::$booted) return;
        self::$booted = true;
        $all = self::all();
        foreach (self::active() as $slug) {
            if (!isset($all[$slug])) continue;
            $err = self::load($all[$slug]);
            if ($err !== '') { self::$errors[$slug] = $err; error_log("Kutubkhanah plugin $slug: $err"); }
        }
        Hooks::action('plugins_loaded');
        Hooks::action('init');
        Hooks::action('kk_init');
    }

    public static function errors(): array { return self::$errors; }

    /** @return array{0:bool,1:string} */
    public static function activate(string $slug): array
    {
        $all = self::all();
        if (!isset($all[$slug])) return [false, __('Plugin not found.')];
        $p = $all[$slug];
        // Try it first: a plugin that fails to load must not be switched on.
        if (!self::$booted) { /* nothing else loaded yet in an API call; fine */ }
        $err = in_array($slug, self::active(), true) ? '' : self::load($p);
        if ($err !== '') return [false, $err];
        self::saveActive(array_merge(self::active(), [$slug]));
        Hooks::action('activate_' . $slug);
        Hooks::action('activate_' . $slug . '/' . $p['main']);
        return [true, ''];
    }

    public static function deactivate(string $slug): bool
    {
        Hooks::action('deactivate_' . $slug);
        self::saveActive(array_diff(self::active(), [$slug]));
        return true;
    }

    public static function delete(string $slug): bool
    {
        if (!self::validSlug($slug) || !isset(self::all()[$slug]) || in_array($slug, self::active(), true)) return false;
        self::rrmdir(self::dir() . $slug);
        return !is_dir(self::dir() . $slug);
    }

    private static function rrmdir(string $d): void
    {
        if (is_link($d) || is_file($d)) { @unlink($d); return; }
        foreach (scandir($d) ?: [] as $f) if ($f !== '.' && $f !== '..') self::rrmdir($d . '/' . $f);
        @rmdir($d);
    }

    /** Install a plugin or theme from an uploaded .zip. @return array{0:bool,1:string} ok, message */
    public static function installZip(string $tmp, string $target = 'plugins'): array
    {
        if (!class_exists(\ZipArchive::class)) return [false, __('The PHP zip extension is not installed.')];
        $z = new \ZipArchive();
        if ($z->open($tmp) !== true) return [false, __('That is not a valid .zip file.')];
        $total = 0;
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string)$z->getNameIndex($i);
            if ($n === '' || str_contains($n, '..') || str_starts_with($n, '/') || preg_match('#^[A-Za-z]:#', $n) || str_contains($n, "\0") || str_starts_with(basename($n), '.')) { $z->close(); return [false, __('The archive holds unsafe paths.')]; }
            $total += (int)($z->statIndex($i)['size'] ?? 0);
            $names[] = $n;
        }
        if ($total > 80 * 1024 * 1024) { $z->close(); return [false, __('The archive is too large.')]; }
        $tops = array_values(array_unique(array_map(fn($n) => explode('/', $n)[0], $names)));
        $strip = '';
        if (count($tops) === 1 && count(array_filter($names, fn($n) => str_contains($n, '/'))) > 0) $strip = $tops[0] . '/';
        $slug = $strip !== '' ? rtrim($strip, '/') : pathinfo($tmp, PATHINFO_FILENAME);
        $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $slug), '-.'));
        $base = rtrim(SB, '/') . '/' . ($target === 'themes' ? 'template' : 'plugins') . '/';
        if (!self::validSlug($slug) || is_dir($base . $slug)) { $z->close(); return [false, sprintf(__('"%s" is already installed or has an unusable name.'), $slug)]; }
        // Executable code is the point of a plugin, but nothing may land outside its own folder.
        mkdir($base . $slug, 0755, true);
        foreach ($names as $i => $n) {
            $rel = $strip !== '' ? substr($n, strlen($strip)) : $n;
            if ($rel === '') continue;
            $dest = $base . $slug . '/' . $rel;
            if (str_ends_with($n, '/')) { if (!is_dir($dest)) mkdir($dest, 0755, true); continue; }
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
            file_put_contents($dest, $z->getFromIndex($i));
        }
        $z->close();
        $ok = $target === 'themes' ? (is_file($base . $slug . '/theme.json') || is_file($base . $slug . '/index.php') || is_file($base . $slug . '/style.css')) : (bool)self::inspect($slug, $base . $slug);
        if (!$ok) { self::rrmdir($base . $slug); return [false, __('That archive is not a Kutubkhanah, WordPress or Omeka Classic ' . ($target === 'themes' ? 'theme.' : 'plugin.'))]; }
        return [true, $slug];
    }

    /* ---------- Admin pages added by plugins ---------- */

    public static function addPage(string $slug, string $title, callable $cb, string $capability = 'manage_options'): void
    {
        self::$pages[$slug] = ['title' => $title, 'cb' => $cb, 'plugin' => self::$loading];
    }

    public static function pages(): array { return self::$pages; }
}
