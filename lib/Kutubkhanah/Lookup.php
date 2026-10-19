<?php
/**
 * Kutubkhanah: metadata lookup by ISBN-13 from Open Library and Google Books.
 * The second source fills whatever the first one missed.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Lookup
{
    public static function blank(): array
    {
        return [
            'title' => '', 'authors' => [], 'publisher' => '', 'publish_date' => '',
            'pages' => '', 'description' => '', 'cover_url' => '', 'source' => '',
        ];
    }

    public static function find(string $isbn13): array
    {
        $sources = Settings::get('source_order') === 'google_first' ? ['google', 'openlibrary'] : ['openlibrary', 'google'];
        $out = self::blank();
        foreach ($sources as $source) {
            $r = $source === 'google' ? self::google($isbn13) : self::openLibrary($isbn13);
            if (!$r) continue;
            foreach (array_keys($out) as $k) {
                if ($k !== 'source' && empty($out[$k]) && !empty($r[$k])) $out[$k] = $r[$k];
            }
            if ($out['source'] === '' && $out['title'] !== '') $out['source'] = $source;
            if ($out['title'] && $out['cover_url'] && $out['description']) break;
        }
        return $out;
    }

    private static function text($s, int $max = 255): string
    {
        return mb_substr(trim(strip_tags((string)$s)), 0, $max);
    }

    public static function openLibrary(string $isbn): ?array
    {
        $key = 'ISBN:' . $isbn;
        $json = Http::json('https://openlibrary.org/api/books?bibkeys=' . rawurlencode($key) . '&format=json&jscmd=data');
        if (empty($json[$key]['title'])) return null;
        $d = $json[$key];
        $title = $d['title'] . (!empty($d['subtitle']) ? ': ' . $d['subtitle'] : '');
        return [
            'title'        => self::text($title, 500),
            'authors'      => array_values(array_filter(array_map(fn($a) => self::text($a['name'] ?? '', 100), $d['authors'] ?? []))),
            'publisher'    => self::text($d['publishers'][0]['name'] ?? '', 100),
            'publish_date' => self::text($d['publish_date'] ?? '', 20),
            'pages'        => !empty($d['number_of_pages']) ? (string)(int)$d['number_of_pages'] : '',
            'description'  => '',
            'cover_url'    => (string)($d['cover']['large'] ?? $d['cover']['medium'] ?? ''),
        ];
    }

    public static function google(string $isbn): ?array
    {
        $url = 'https://www.googleapis.com/books/v1/volumes?maxResults=1&q=isbn:' . rawurlencode($isbn);
        $key = trim((string)Settings::get('google_key'));
        if ($key !== '') $url .= '&key=' . rawurlencode($key);
        $json = Http::json($url);
        if (empty($json['items'][0]['volumeInfo']['title'])) return null;
        $v = $json['items'][0]['volumeInfo'];
        $cover = '';
        foreach (['large', 'medium', 'thumbnail', 'smallThumbnail'] as $size) {
            if (!empty($v['imageLinks'][$size])) {
                $cover = str_replace('&edge=curl', '', preg_replace('#^http://#', 'https://', $v['imageLinks'][$size]));
                break;
            }
        }
        $title = $v['title'] . (!empty($v['subtitle']) ? ': ' . $v['subtitle'] : '');
        return [
            'title'        => self::text($title, 500),
            'authors'      => array_values(array_filter(array_map(fn($a) => self::text($a, 100), $v['authors'] ?? []))),
            'publisher'    => self::text($v['publisher'] ?? '', 100),
            'publish_date' => self::text($v['publishedDate'] ?? '', 20),
            'pages'        => !empty($v['pageCount']) ? (string)(int)$v['pageCount'] : '',
            'description'  => self::text($v['description'] ?? '', 5000),
            'cover_url'    => $cover,
        ];
    }
}
