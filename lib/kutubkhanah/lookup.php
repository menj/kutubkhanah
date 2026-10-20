<?php
/**
 * Kutubkhanah: book details by ISBN from free, open book databases.
 *
 * Sources, in order of trust (each one only fills what the earlier ones left empty):
 *   openlibrary  Open Library (Internet Archive): edition, work description, subjects, classes, cover
 *   loc          Library of Congress catalogue (SRU, MARC 21)
 *   google       Google Books
 *   hathitrust   HathiTrust Bibliographic API (identifiers, publication date, full-view link)
 *   internetarchive  Internet Archive search (title, publisher, date, link to a free copy)
 *   k10plus      K10plus union catalogue of German and international libraries (SRU, MARC 21)
 *   custom       Any other SRU catalogue that returns MARCXML (e.g. a national library)
 * All ISBN requests run in parallel, so a lookup takes about as long as the slowest source.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Lookup
{
    /** Source key => label (Settings > Lookup). */
    public static function sources(): array
    {
        return [
            'openlibrary' => 'Open Library',
            'loc'         => 'Library of Congress',
            'google'      => 'Google Books',
            'hathitrust'  => 'HathiTrust',
            'internetarchive' => 'Internet Archive',
            'k10plus'     => 'K10plus (German and international libraries)',
            'custom'      => __('Custom SRU catalogue'),
        ];
    }

    public static function blank(): array
    {
        return [
            'title' => '', 'authors' => [], 'publisher' => '', 'place' => '', 'publish_date' => '',
            'edition' => '', 'pages' => '', 'collation' => '', 'language' => '', 'description' => '',
            'subjects' => [], 'dewey' => '', 'lcc' => '', 'lccn' => '', 'oclc' => '', 'series' => '',
            'alt_title' => '', 'digital_link' => '', 'cover_url' => '', 'openlibrary_id' => '', 'google_books_id' => '',
            'source' => '', 'sources' => [],
        ];
    }

    /** Enabled sources, in trust order. */
    public static function enabled(): array
    {
        $on = [];
        foreach (array_keys(self::sources()) as $key) {
            if ($key === 'custom') {
                if (self::customUrl() !== '' && Settings::get('src_custom')) $on[] = $key;
            } elseif (Settings::get('src_' . $key)) {
                $on[] = $key;
            }
        }
        return $on;
    }

    public static function customUrl(): string
    {
        $u = trim((string)Settings::get('sru_custom_url'));
        return preg_match('#^https?://#i', $u) ? $u : '';
    }

    /** SRU searchRetrieve URL for an ISBN-13 (and its ISBN-10, which older records carry). */
    public static function sruUrl(string $base, string $index, string $isbn13, string $schema = 'marcxml'): string
    {
        $isbn10 = Isbn::to10($isbn13);
        $query = $index . '=' . $isbn13 . ($isbn10 !== '' ? ' or ' . $index . '=' . $isbn10 : '');
        $sep = str_contains($base, '?') ? '&' : '?';
        return $base . $sep . 'version=1.1&operation=searchRetrieve&maximumRecords=1&recordSchema=' . rawurlencode($schema)
            . '&query=' . rawurlencode($query);
    }

    /** The URL each source is asked, keyed by source. */
    public static function requests(string $isbn13): array
    {
        $urls = [];
        foreach (self::enabled() as $src) {
            switch ($src) {
                case 'openlibrary':
                    $urls['openlibrary'] = 'https://openlibrary.org/api/books?bibkeys=' . rawurlencode('ISBN:' . $isbn13) . '&format=json&jscmd=data';
                    $urls['openlibrary_edition'] = 'https://openlibrary.org/isbn/' . rawurlencode($isbn13) . '.json';
                    break;
                case 'loc':
                    $urls['loc'] = self::sruUrl('http://lx2.loc.gov:210/LCDB', 'bath.isbn', $isbn13);
                    break;
                case 'google':
                    $u = 'https://www.googleapis.com/books/v1/volumes?maxResults=1&q=isbn:' . rawurlencode($isbn13);
                    $key = trim((string)Settings::get('google_key'));
                    $urls['google'] = $key !== '' ? $u . '&key=' . rawurlencode($key) : $u;
                    break;
                case 'hathitrust':
                    $urls['hathitrust'] = 'https://catalog.hathitrust.org/api/volumes/brief/isbn/' . rawurlencode($isbn13) . '.json';
                    break;
                case 'internetarchive':
                    $urls['internetarchive'] = 'https://archive.org/advancedsearch.php?output=json&rows=1&q=' . rawurlencode('isbn:' . $isbn13)
                        . '&fl%5B%5D=identifier&fl%5B%5D=title&fl%5B%5D=creator&fl%5B%5D=publisher&fl%5B%5D=date&fl%5B%5D=language&fl%5B%5D=description';
                    break;
                case 'k10plus':
                    $urls['k10plus'] = self::sruUrl('https://sru.k10plus.de/opac-de-627', 'pica.isb', $isbn13);
                    break;
                case 'custom':
                    $urls['custom'] = self::sruUrl(self::customUrl(), (string)(Settings::get('sru_custom_index') ?: 'isbn'), $isbn13);
                    break;
            }
        }
        return $urls;
    }

    public static function find(string $isbn13): array
    {
        $responses = Http::many(self::requests($isbn13));
        $records = [];
        foreach (self::enabled() as $src) {
            $r = match ($src) {
                'openlibrary' => self::openLibrary($responses['openlibrary'] ?? [0, ''], $responses['openlibrary_edition'] ?? [0, '']),
                'google'      => self::google($responses['google'] ?? [0, '']),
                'hathitrust'  => self::hathiTrust($responses['hathitrust'] ?? [0, '']),
                'internetarchive' => self::internetArchive($responses['internetarchive'] ?? [0, '']),
                default       => isset($responses[$src]) && $responses[$src][0] === 200 ? Marc::fromSru($responses[$src][1]) : null,
            };
            if ($r) $records[$src] = $r;
        }
        $out = self::merge($records);

        // Open Library keeps descriptions and subjects on the "work"; ask for it only when still missing.
        if (!empty($records['openlibrary']['work_key']) && ($out['description'] === '' || !$out['subjects'])) {
            $work = Http::json('https://openlibrary.org' . $records['openlibrary']['work_key'] . '.json');
            if ($work) {
                $d = $work['description'] ?? '';
                if ($out['description'] === '' && ($d = is_array($d) ? ($d['value'] ?? '') : $d)) $out['description'] = self::text($d, 5000);
                if (!$out['subjects']) $out['subjects'] = self::subjects(array_slice((array)($work['subjects'] ?? []), 0, 10));
            }
        }
        return $out;
    }

    /** Combine records: scalars from the most trusted source that has them, subjects pooled. */
    public static function merge(array $records): array
    {
        $out = self::blank();
        foreach ($records as $src => $r) {
            $used = false;
            foreach ($out as $k => $v) {
                if (in_array($k, ['source', 'sources', 'subjects'], true) || !array_key_exists($k, $r)) continue;
                if (empty($v) && !empty($r[$k])) { $out[$k] = $r[$k]; $used = true; }
            }
            foreach ((array)($r['subjects'] ?? []) as $s) {
                if (count($out['subjects']) >= 12) break;
                if (!in_array(mb_strtolower($s), array_map('mb_strtolower', $out['subjects']), true)) { $out['subjects'][] = $s; $used = true; }
            }
            if ($used) $out['sources'][] = $src;
        }
        $out['source'] = $out['sources'][0] ?? '';
        $out['subjects'] = self::subjects($out['subjects']);
        return $out;
    }

    public static function text($s, int $max = 255): string
    {
        $s = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string)$s)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_substr(trim(preg_replace("/[ \t]+/u", ' ', $s)), 0, $max);
    }

    /** Clean subject headings, 50 characters each (the the base system topic limit). */
    private static function subjects(array $list): array
    {
        $out = [];
        foreach ($list as $s) {
            $s = is_array($s) ? ($s['name'] ?? '') : $s;
            $s = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$s)), " .,;"), 0, 50);
            if ($s !== '' && !in_array(mb_strtolower($s), array_map('mb_strtolower', $out), true)) $out[] = $s;
        }
        return array_slice($out, 0, 12);
    }

    /** ISO 639-2 ("eng", "may") or 639-1 ("en") → the 2-letter code the base system uses. */
    public static function language(string $code): string
    {
        $code = strtolower(trim(preg_replace('#^/languages/#', '', $code)));
        if ($code === '' || $code === 'und' || $code === 'mul' || $code === 'zxx') return '';
        $map = [
            'eng' => 'en', 'ara' => 'ar', 'may' => 'ms', 'msa' => 'ms', 'ind' => 'id', 'fre' => 'fr', 'fra' => 'fr',
            'ger' => 'de', 'deu' => 'de', 'spa' => 'es', 'ita' => 'it', 'por' => 'pt', 'rus' => 'ru', 'chi' => 'zh',
            'zho' => 'zh', 'jpn' => 'ja', 'kor' => 'ko', 'per' => 'fa', 'fas' => 'fa', 'urd' => 'ur', 'tur' => 'tr',
            'dut' => 'nl', 'nld' => 'nl', 'heb' => 'he', 'hin' => 'hi', 'ben' => 'bn', 'tam' => 'ta', 'tha' => 'th',
            'vie' => 'vi', 'lat' => 'la', 'swe' => 'sv', 'nor' => 'no', 'dan' => 'da', 'fin' => 'fi', 'pol' => 'pl',
            'gre' => 'el', 'ell' => 'el', 'cze' => 'cs', 'ces' => 'cs', 'hun' => 'hu', 'rum' => 'ro', 'ron' => 'ro',
            'ukr' => 'uk', 'bos' => 'bs', 'alb' => 'sq', 'sqi' => 'sq', 'pus' => 'ps', 'kur' => 'ku', 'som' => 'so',
            'swa' => 'sw', 'hau' => 'ha', 'tgl' => 'tl', 'jav' => 'jv',
        ];
        if (isset($map[$code])) return $map[$code];
        return preg_match('/^[a-z]{2}$/', $code) ? $code : '';
    }

    /** Human name for a language code, used when the base system doesn't have the language yet. */
    public static function languageName(string $code): string
    {
        $names = [
            'en' => 'English', 'ar' => 'Arabic', 'ms' => 'Malay', 'id' => 'Indonesia', 'fr' => 'French', 'de' => 'German',
            'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Chinese', 'ja' => 'Japanese',
            'ko' => 'Korean', 'fa' => 'Persian', 'ur' => 'Urdu', 'tr' => 'Turkish', 'nl' => 'Dutch', 'he' => 'Hebrew',
            'hi' => 'Hindi', 'bn' => 'Bengali', 'ta' => 'Tamil', 'th' => 'Thai', 'vi' => 'Vietnamese', 'la' => 'Latin',
            'sv' => 'Swedish', 'no' => 'Norwegian', 'da' => 'Danish', 'fi' => 'Finnish', 'pl' => 'Polish', 'el' => 'Greek',
            'cs' => 'Czech', 'hu' => 'Hungarian', 'ro' => 'Romanian', 'uk' => 'Ukrainian', 'bs' => 'Bosnian', 'sq' => 'Albanian',
            'ps' => 'Pashto', 'ku' => 'Kurdish', 'so' => 'Somali', 'sw' => 'Swahili', 'ha' => 'Hausa', 'tl' => 'Tagalog', 'jv' => 'Javanese',
        ];
        if (isset($names[$code])) return $names[$code];
        if (class_exists('Locale')) {
            $n = \Locale::getDisplayLanguage($code, 'en');
            if ($n !== '' && $n !== $code) return mb_substr($n, 0, 20);
        }
        return strtoupper($code);
    }

    /* ---------- Sources ---------- */

    /** Open Library: the "data" view (names resolved) plus the raw edition (languages, series, work link). */
    public static function openLibrary(array $dataResponse, array $editionResponse): ?array
    {
        $json = Http::decode($dataResponse) ?? [];
        $d = $json ? reset($json) : [];
        $e = Http::decode($editionResponse) ?? [];
        if (empty($d['title']) && empty($e['title'])) return null;

        $title = ($d['title'] ?? $e['title']) . (($sub = $d['subtitle'] ?? $e['subtitle'] ?? '') !== '' ? ': ' . $sub : '');
        $class = $d['classifications'] ?? [];
        $desc = $e['description'] ?? '';
        $desc = is_array($desc) ? ($desc['value'] ?? '') : $desc;
        $series = $e['series'][0] ?? '';
        $olid = '';
        if (preg_match('#/books/(OL\d+M)#', (string)($e['key'] ?? $d['key'] ?? ''), $m)) $olid = $m[1];
        $cover = (string)($d['cover']['large'] ?? $d['cover']['medium'] ?? '');
        if ($cover === '' && !empty($e['covers'][0]) && (int)$e['covers'][0] > 0) $cover = 'https://covers.openlibrary.org/b/id/' . (int)$e['covers'][0] . '-L.jpg';

        return [
            'title'          => self::text($title, 500),
            'authors'        => array_values(array_filter(array_map(fn($a) => self::text($a['name'] ?? '', 100), $d['authors'] ?? []))),
            'publisher'      => self::text($d['publishers'][0]['name'] ?? $e['publishers'][0] ?? '', 100),
            'place'          => self::text($d['publish_places'][0]['name'] ?? $e['publish_places'][0] ?? '', 30),
            'publish_date'   => self::text($d['publish_date'] ?? $e['publish_date'] ?? '', 20),
            'edition'        => Marc::clean(self::text($e['edition_name'] ?? '', 50)),
            'pages'          => !empty($d['number_of_pages']) ? (string)(int)$d['number_of_pages'] : (!empty($e['number_of_pages']) ? (string)(int)$e['number_of_pages'] : ''),
            'collation'      => self::text(rtrim((string)($d['pagination'] ?? $e['pagination'] ?? ''), ' ;:'), 100),
            'language'       => self::language((string)($e['languages'][0]['key'] ?? '')),
            'description'    => self::text($desc, 5000),
            'subjects'       => self::subjects(array_slice((array)($d['subjects'] ?? []), 0, 10)),
            'dewey'          => str_replace('/', '', self::text($class['dewey_decimal_class'][0] ?? $e['dewey_decimal_class'][0] ?? '', 40)),
            'lcc'            => self::text($class['lc_classifications'][0] ?? $e['lc_classifications'][0] ?? '', 60),
            'lccn'           => self::text($d['identifiers']['lccn'][0] ?? $e['lccn'][0] ?? '', 20),
            'oclc'           => self::text($d['identifiers']['oclc'][0] ?? $e['oclc_numbers'][0] ?? '', 30),
            'series'         => self::text(is_array($series) ? '' : $series, 200),
            'cover_url'      => $cover,
            'openlibrary_id' => $olid,
            'work_key'       => (string)($e['works'][0]['key'] ?? ($d['works'][0]['key'] ?? '')),
        ];
    }

    /** Google Books record for one ISBN (used by the cover fetcher). */
    public static function googleByIsbn(string $isbn13): ?array
    {
        $urls = self::requests($isbn13);
        if (!isset($urls['google'])) return null;
        return self::google(Http::get($urls['google']));
    }

    public static function google(array $response): ?array
    {
        $json = Http::decode($response);
        if (empty($json['items'][0]['volumeInfo']['title'])) return null;
        $item = $json['items'][0];
        $v = $item['volumeInfo'];
        $cover = '';
        foreach (['large', 'medium', 'thumbnail', 'smallThumbnail'] as $size) {
            if (!empty($v['imageLinks'][$size])) {
                $cover = str_replace('&edge=curl', '', preg_replace('#^http://#', 'https://', $v['imageLinks'][$size]));
                break;
            }
        }
        $title = $v['title'] . (!empty($v['subtitle']) ? ': ' . $v['subtitle'] : '');
        return [
            'title'           => self::text($title, 500),
            'authors'         => array_values(array_filter(array_map(fn($a) => self::text($a, 100), $v['authors'] ?? []))),
            'publisher'       => self::text($v['publisher'] ?? '', 100),
            'publish_date'    => self::text($v['publishedDate'] ?? '', 20),
            'pages'           => !empty($v['pageCount']) ? (string)(int)$v['pageCount'] : '',
            'language'        => self::language((string)($v['language'] ?? '')),
            'description'     => self::text($v['description'] ?? '', 5000),
            'subjects'        => self::subjects((array)($v['categories'] ?? [])),
            'cover_url'       => $cover,
            'google_books_id' => self::text($item['id'] ?? '', 30),
        ];
    }

    /** HathiTrust brief record: identifiers, publication date and a link when a full-view copy exists. */
    public static function hathiTrust(array $response): ?array
    {
        $json = Http::decode($response);
        $rec = is_array($json['records'] ?? null) ? reset($json['records']) : null;
        if (!$rec) return null;
        $link = '';
        foreach ((array)($json['items'] ?? []) as $it) {
            if (stripos((string)($it['usRightsString'] ?? ''), 'full view') !== false && !empty($it['htid'])) {
                $link = 'https://babel.hathitrust.org/cgi/pt?id=' . rawurlencode((string)$it['htid']);
                break;
            }
        }
        $r = [
            'title'        => self::text((string)($rec['titles'][0] ?? ''), 500),
            'publish_date' => self::text((string)($rec['publishDates'][0] ?? ''), 20),
            'oclc'         => self::text((string)($rec['oclcs'][0] ?? ''), 30),
            'lccn'         => self::text((string)($rec['lccns'][0] ?? ''), 20),
            'digital_link' => $link,
        ];
        return array_filter($r) ? $r : null;
    }

    /** Internet Archive advanced search: one matching item, with a link to read or borrow it. */
    public static function internetArchive(array $response): ?array
    {
        $json = Http::decode($response);
        $d = $json['response']['docs'][0] ?? null;
        if (!$d || empty($d['identifier']) || !preg_match('/^[A-Za-z0-9._-]+$/', (string)$d['identifier'])) return null;
        $one = fn($v) => is_array($v) ? (string)($v[0] ?? '') : (string)$v;
        $creator = $d['creator'] ?? [];
        return [
            'title'        => self::text($one($d['title'] ?? ''), 500),
            'authors'      => array_values(array_filter(array_map(fn($a) => self::text((string)$a, 100), is_array($creator) ? $creator : [$creator]))),
            'publisher'    => self::text($one($d['publisher'] ?? ''), 100),
            'publish_date' => self::text(substr($one($d['date'] ?? ''), 0, 10), 20),
            'description'  => self::text(strip_tags($one($d['description'] ?? '')), 5000),
            'digital_link' => 'https://archive.org/details/' . $d['identifier'],
        ];
    }
}
