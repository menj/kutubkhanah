<?php
/**
 * Kutubkhanah: Omeka Classic compatibility layer for plugins.
 *
 * Runs plugins that follow the Omeka Classic plugin API (plugin.ini, a <Name>Plugin class extending
 * Omeka_Plugin_AbstractPlugin, hooks, filters, options, queue_css_file/queue_js_file). Omeka's own models, routes and
 * Zend pieces do not exist here, so plugins that add item types, element sets or records will not work.
 * Omeka S modules are a different system and are not supported. See docs/plugins.md.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Hooks;

class Omeka_Plugin_Exception extends Exception {}

/** Minimal database handle for $this->_db. */
class Kk_Omeka_Db
{
    public string $prefix = 'omeka_';
    public function __get($table) { return $this->prefix . strtolower((string)preg_replace('/(?<!^)([A-Z])/', '_$1', (string)$table)) . 's'; }
    public function query($sql, $bind = [])
    {
        $st = \SLiMS\DB::getInstance()->prepare((string)$sql);
        $st->execute(array_values((array)$bind));
        return $st;
    }
    public function exec($sql) { return \SLiMS\DB::getInstance()->exec((string)$sql); }
    public function fetchAll($sql, $bind = []) { return $this->query($sql, $bind)->fetchAll(\PDO::FETCH_ASSOC); }
    public function fetchOne($sql, $bind = []) { return $this->query($sql, $bind)->fetchColumn(); }
    public function lastInsertId() { return \SLiMS\DB::getInstance()->lastInsertId(); }
    public function getTable($name) { throw new Omeka_Plugin_Exception('Omeka record tables are not available in Kutubkhanah.'); }
}

abstract class Omeka_Plugin_AbstractPlugin
{
    protected $_db;
    protected $_hooks;
    protected $_filters;
    protected $_options;

    public function __construct() { $this->_db = new Kk_Omeka_Db(); }

    public function setUp()
    {
        foreach ((array)$this->_hooks as $callback => $hook) {
            if (!is_string($callback)) $callback = 'hook' . Kk_Omeka::camel((string)$hook);
            if (!is_callable([$this, $callback])) throw new Omeka_Plugin_Exception('Hook callback "' . $callback . '" does not exist.');
            add_plugin_hook((string)$hook, [$this, $callback]);
        }
        foreach ((array)$this->_filters as $callback => $filter) {
            if (!is_string($callback)) $callback = 'filter' . Kk_Omeka::camel((string)$filter);
            if (!is_callable([$this, $callback])) throw new Omeka_Plugin_Exception('Filter callback "' . $callback . '" does not exist.');
            Hooks::add((string)$filter, [$this, $callback], 10, 2);
        }
    }

    protected function _installOptions() { foreach ((array)$this->_options as $n => $v) if (is_string($n)) set_option($n, $v); }
    protected function _uninstallOptions() { foreach ((array)$this->_options as $n => $v) delete_option(is_string($n) ? $n : $v); }
}

class Kk_Omeka
{
    public static array $plugins = [], $queue = [], $ini = [];
    private static bool $wired = false;

    public static function camel(string $s): string { return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $s))); }

    public static function loadPlugin(string $slug, string $name, string $dir, string $file): void
    {
        self::wire();
        self::$ini[$slug] = @parse_ini_file($dir . 'plugin.ini') ?: [];
        require_once $file;
        $class = self::camel($slug) . 'Plugin';
        if (!class_exists($class)) throw new Omeka_Plugin_Exception("Class $class was not found in " . basename($file));
        $obj = new $class();
        $obj->setUp();
        self::$plugins[$slug] = ['obj' => $obj, 'dir' => $dir, 'name' => $name];
        Hooks::add('activate_' . $slug, fn() => fire_plugin_hook('install', ['plugin_id' => 0, 'version' => self::$ini[$slug]['version'] ?? '']), 10, 0);
        Hooks::add('deactivate_' . $slug, fn() => fire_plugin_hook('uninstall', ['plugin_id' => 0]), 10, 0);
    }

    private static function wire(): void
    {
        if (self::$wired) return;
        self::$wired = true;
        Hooks::add('kk_init', function () { fire_plugin_hook('initialize'); fire_plugin_hook('define_routes', ['router' => new stdClass()]); });
        Hooks::add('kk_head', function () {
            fire_plugin_hook('public_head', ['view' => new Kk_Omeka_View()]);
            foreach (self::$queue as [$type, $url]) echo $type === 'css' ? '<link rel="stylesheet" href="' . html_escape($url) . '">' . "\n" : '<script src="' . html_escape($url) . '"></script>' . "\n";
        });
        Hooks::add('kk_footer', fn() => fire_plugin_hook('public_footer', ['view' => new Kk_Omeka_View()]));
        Hooks::add('kk_book_page', function ($id, $title = '') {
            $item = new Kk_Omeka_Item((int)$id, (string)$title);
            fire_plugin_hook('public_items_show', ['view' => new Kk_Omeka_View(), 'item' => $item]);
        }, 10, 2);
        Hooks::add('kk_book_saved', function ($id) { fire_plugin_hook('after_save_item', ['record' => new Kk_Omeka_Item((int)$id), 'insert' => false]); });
    }
}

class Kk_Omeka_View
{
    public function __call($n, $a) { return ''; }
    public function escape($s) { return html_escape($s); }
}

class Kk_Omeka_Item
{
    public int $id;
    public string $title;
    public function __construct(int $id, string $title = '') { $this->id = $id; $this->title = $title; }
    public function getProperty($k) { return $this->$k ?? null; }
}

function add_plugin_hook($hook, $cb) { Hooks::add('omeka:' . $hook, $cb, 10, 1); }
function fire_plugin_hook($name, array $args = []) { Hooks::action('omeka:' . $name, $args); }
function get_plugin_hook_output($name, array $args = []) { ob_start(); fire_plugin_hook($name, $args); return (string)ob_get_clean(); }
function set_option($name, $value) { return update_option($name, $value); }
function html_escape($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function plugin_is_active($name, $version = null) { return in_array((string)$name, \SLiMS\Kutubkhanah\Plugins::active(), true); }
function get_plugin_ini($plugin, $key) { return Kk_Omeka::$ini[$plugin][$key] ?? null; }
function public_url($path = '') { return home_url($path); }
function url($options = [], $name = null, $queryParams = [], $reset = false, $encode = true) { return home_url(is_string($options) ? $options : ''); }
function get_view() { return new Kk_Omeka_View(); }
function plugin_web_url(string $slug, string $kind, string $file): string { return home_url('plugins/' . $slug . '/views/public/' . $kind . '/' . ltrim($file, '/')); }
function queue_css_url($url, $media = 'all') { Kk_Omeka::$queue[] = ['css', $url]; }
function queue_js_url($url, $type = 'text/javascript') { Kk_Omeka::$queue[] = ['js', $url]; }
function queue_css_file($file, $media = 'all', $conditional = false, $dir = 'css')
{
    foreach ((array)$file as $f) queue_css_url(plugin_web_url(\SLiMS\Kutubkhanah\Plugins::$loading ?: (array_key_last(Kk_Omeka::$plugins) ?: ''), 'css', $f . (str_ends_with($f, '.css') ? '' : '.css')));
}
function queue_js_file($file, $dir = 'javascripts', $options = [])
{
    foreach ((array)$file as $f) queue_js_url(plugin_web_url(\SLiMS\Kutubkhanah\Plugins::$loading ?: (array_key_last(Kk_Omeka::$plugins) ?: ''), 'javascripts', $f . (str_ends_with($f, '.js') ? '' : '.js')));
}
