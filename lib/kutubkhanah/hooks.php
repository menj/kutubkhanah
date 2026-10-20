<?php
/**
 * Kutubkhanah: actions and filters (the same model as WordPress), plus shortcodes.
 * Plugins hook in with Hooks::addAction() / Hooks::addFilter(); the WordPress and Omeka layers map onto these.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Hooks
{
    /** @var array<string,array<int,array<int,array{0:callable,1:int}>>> tag => priority => [[callback, accepted args]] */
    private static array $hooks = [];
    private static array $done = [];
    private static array $shortcodes = [];

    public static function add(string $tag, callable $cb, int $priority = 10, int $args = 1): bool
    {
        self::$hooks[$tag][$priority][] = [$cb, $args];
        ksort(self::$hooks[$tag]);
        return true;
    }

    public static function addAction(string $tag, callable $cb, int $priority = 10, int $args = 1): bool { return self::add($tag, $cb, $priority, $args); }
    public static function addFilter(string $tag, callable $cb, int $priority = 10, int $args = 1): bool { return self::add($tag, $cb, $priority, $args); }

    public static function remove(string $tag, callable $cb, int $priority = 10): bool
    {
        foreach (self::$hooks[$tag][$priority] ?? [] as $i => [$c]) {
            if ($c === $cb || (is_array($c) && is_array($cb) && $c[0] === $cb[0] && $c[1] === $cb[1])) { unset(self::$hooks[$tag][$priority][$i]); return true; }
        }
        return false;
    }

    public static function has(string $tag, ?callable $cb = null)
    {
        if (empty(self::$hooks[$tag])) return false;
        if ($cb === null) return true;
        foreach (self::$hooks[$tag] as $prio => $list) foreach ($list as [$c]) if ($c === $cb) return $prio;
        return false;
    }

    /** Run every callback for an event. A failing plugin never takes the page down. */
    public static function action(string $tag, ...$args): void
    {
        self::$done[$tag] = (self::$done[$tag] ?? 0) + 1;
        foreach (self::$hooks[$tag] ?? [] as $list) {
            foreach ($list as [$cb, $n]) {
                try { $cb(...array_slice($args, 0, max(0, $n))); } catch (\Throwable $e) { error_log("Kutubkhanah hook $tag: " . $e->getMessage()); }
            }
        }
    }

    /** Pass a value through every callback for a filter. */
    public static function filter(string $tag, $value, ...$args)
    {
        self::$done[$tag] = (self::$done[$tag] ?? 0) + 1;
        foreach (self::$hooks[$tag] ?? [] as $list) {
            foreach ($list as [$cb, $n]) {
                try { $value = $cb(...array_slice(array_merge([$value], $args), 0, max(1, $n))); } catch (\Throwable $e) { error_log("Kutubkhanah filter $tag: " . $e->getMessage()); }
            }
        }
        return $value;
    }

    public static function did(string $tag): int { return self::$done[$tag] ?? 0; }

    /* ---------- Shortcodes: [tag attr="x"]content[/tag] ---------- */

    public static function addShortcode(string $tag, callable $cb): void { self::$shortcodes[$tag] = $cb; }
    public static function removeShortcode(string $tag): void { unset(self::$shortcodes[$tag]); }
    public static function shortcodeExists(string $tag): bool { return isset(self::$shortcodes[$tag]); }

    public static function shortcodes(string $text): string
    {
        if (!self::$shortcodes || !str_contains($text, '[')) return $text;
        $names = implode('|', array_map('preg_quote', array_keys(self::$shortcodes)));
        $re = '/\[(' . $names . ')((?:\s+[^\]]*?)?)\s*(?:\/\]|\](?:(.*?)\[\/\1\])?)/s';
        return (string)preg_replace_callback($re, function ($m) {
            $atts = self::parseAtts($m[2] ?? '');
            try { return (string)(self::$shortcodes[$m[1]])($atts, $m[3] ?? null, $m[1]); }
            catch (\Throwable $e) { error_log('Kutubkhanah shortcode ' . $m[1] . ': ' . $e->getMessage()); return ''; }
        }, $text);
    }

    public static function parseAtts(string $s): array
    {
        $out = [];
        if (preg_match_all('/([A-Za-z0-9_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))|"([^"]*)"|(\S+)/', trim($s), $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (!empty($m[1])) $out[strtolower($m[1])] = $m[2] !== '' ? $m[2] : ($m[3] ?? '') . ($m[4] ?? '');
                elseif (($m[5] ?? '') !== '' || ($m[6] ?? '') !== '') $out[] = ($m[5] ?? '') !== '' ? $m[5] : $m[6];
            }
        }
        return $out;
    }

    /** shortcode_atts(): defaults merged with what was given. */
    public static function atts(array $defaults, $given): array
    {
        $given = (array)$given;
        $out = [];
        foreach ($defaults as $k => $v) $out[$k] = array_key_exists($k, $given) ? $given[$k] : $v;
        return $out;
    }
}
