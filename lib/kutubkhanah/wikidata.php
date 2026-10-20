<?php
/**
 * Kutubkhanah: author and book details from Wikidata (CC0), saved on this server.
 * Items are found by identifier first (ISNI for authors; ISBN and OCLC for books), then by name or title and
 * checked against the identifiers or "human" before anything is saved. Existing values are never overwritten.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Wikidata
{
    const API = 'https://www.wikidata.org/w/api.php';
    const REST = 'https://www.wikidata.org/w/rest.php/wikibase/v1/entities/items/';

    /** Item ids from a search expression, e.g. haswbstatement:P213=0000... */
    private static function statement(string $prop, string $value): array
    {
        $d = Http::json(self::API . '?action=query&list=search&format=json&srlimit=3&srsearch=' . rawurlencode('haswbstatement:' . $prop . '=' . $value));
        $out = [];
        foreach ($d['query']['search'] ?? [] as $r) if (preg_match('/^Q\d+$/', (string)($r['title'] ?? ''))) $out[] = $r['title'];
        return $out;
    }

    /** Item ids whose label matches a name or title. */
    private static function byText(string $text, string $lang = 'en'): array
    {
        if ($text === '') return [];
        $d = Http::json(self::API . '?action=wbsearchentities&format=json&type=item&limit=5&language=' . rawurlencode($lang) . '&uselang=' . rawurlencode($lang) . '&search=' . rawurlencode($text));
        $out = [];
        foreach ($d['search'] ?? [] as $r) if (preg_match('/^Q\d+$/', (string)($r['id'] ?? ''))) $out[] = $r['id'];
        return $out;
    }

    private static function item(string $q): ?array
    {
        return preg_match('/^Q\d+$/', $q) ? Http::json(self::REST . $q) : null;
    }

    private static function strings(array $item, string $prop): array
    {
        $out = [];
        foreach ($item['statements'][$prop] ?? [] as $s) {
            $c = $s['value']['content'] ?? null;
            if (is_string($c)) $out[] = $c;
            elseif (is_array($c) && isset($c['text'])) $out[] = (string)$c['text'];
            elseif (is_array($c) && isset($c['time'])) $out[] = (string)$c['time'];
        }
        return $out;
    }

    private static function year(array $item, string $prop): string
    {
        $t = self::strings($item, $prop)[0] ?? '';
        return preg_match('/^([+-])0*(\d{1,4})-/', $t, $m) ? ($m[1] === '-' ? '-' : '') . $m[2] : '';
    }

    private static function label(string $q, string $lang = 'en'): string
    {
        [$code, $body] = Http::get(self::REST . $q . '/labels/' . rawurlencode($lang));
        $v = $code === 200 ? json_decode($body, true) : null;
        return is_string($v) ? $v : '';
    }

    private static function digits(string $s): string { return preg_replace('/[^0-9Xx]/', '', $s); }

    // ----- Authors -----

    /** Look one author up on Wikidata and fill in what is empty. Returns true when an item was matched. */
    public static function author(int $id): bool
    {
        $rows = Library::rows('SELECT author_name FROM mst_author WHERE author_id = ?', [$id]);
        if (!$rows) return false;
        $m = Authors::meta($id);
        if (($m['reviewed'] ?? '') === 'r') return false;
        $db = DB::getInstance();
        $now = date('Y-m-d');
        $isni = self::digits((string)($m['isni'] ?? ''));
        $q = strtoupper(trim((string)($m['wd_id'] ?? '')));
        $cands = preg_match('/^Q\d+$/', $q) ? [$q] : [];
        if (!$cands && strlen($isni) === 16) {
            $cands = self::statement('P213', trim(chunk_split($isni, 4, ' ')));
            if (!$cands) $cands = self::statement('P213', $isni);
        }
        if (!$cands) {
            $name = trim((string)($m['search_name'] ?? ''));
            if ($name === '') {
                $name = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', (string)$rows[0]['author_name']));
                if (!preg_match('/[\x{0600}-\x{06FF}\x{3400}-\x{9FFF}]/u', $name) && preg_match('/^([^,]+),\s*(.+)$/', $name, $mm)) $name = trim($mm[2] . ' ' . $mm[1]);
            }
            $cands = self::byText($name);
            if (!$cands && ($nat = trim((string)($m['native_name'] ?? ''))) !== '') {
                $lang = preg_match('/\p{Arabic}/u', $nat) ? 'ar' : (preg_match('/\p{Han}/u', $nat) ? 'zh' : 'en');
                $cands = self::byText($nat, $lang);
            }
        }
        $hit = null;
        foreach (array_slice($cands, 0, 3) as $c) {
            $it = self::item($c);
            if (!$it) continue;
            $isHuman = in_array('Q5', array_map(fn($s) => (string)($s['value']['content'] ?? ''), $it['statements']['P31'] ?? []), true);
            if ($isHuman) { $hit = [$c, $it]; break; }
        }
        self::ensureAuthor($id);
        if (!$hit) {
            $db->prepare('UPDATE kk_author SET wd_tried = ? WHERE author_id = ?')->execute([$now, $id]);
            return false;
        }
        [$q, $it] = $hit;
        $labels = $it['labels'] ?? [];
        $set = ['wd_id' => $q, 'wd_label' => (string)($labels['en'] ?? reset($labels) ?: ''), 'wd_tried' => $now];
        if (($m['native_name'] ?? '') === '') {
            foreach (['ar', 'zh', 'zh-hans', 'zh-cn'] as $l) if (!empty($labels[$l])) { $set['native_name'] = (string)$labels[$l]; break; }
        }
        if (($m['birth_year'] ?? '') === '' && ($y = self::year($it, 'P569')) !== '') $set['birth_year'] = $y;
        if (($m['death_year'] ?? '') === '' && ($y = self::year($it, 'P570')) !== '') $set['death_year'] = $y;
        if (($m['nationality'] ?? '') === '' && ($c = self::strings($it, 'P27')[0] ?? '') !== '') $set['nationality'] = mb_substr(self::label($c), 0, 120);
        if (($m['external_url'] ?? '') === '') $set['external_url'] = 'https://www.wikidata.org/wiki/' . $q;
        if (($m['isni'] ?? '') === '' && ($i = self::strings($it, 'P213')[0] ?? '') !== '') $set['isni'] = mb_substr($i, 0, 30);
        if (($m['bio'] ?? '') === '' && !empty($it['descriptions']['en'])) {
            $set['bio'] = mb_substr((string)$it['descriptions']['en'], 0, 500);
            $set['bio_source'] = 'Wikidata';
        }
        if (($m['photo'] ?? '') === '' && ($f = self::strings($it, 'P18')[0] ?? '') !== '') {
            $photo = Covers::download('https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode(str_replace(' ', '_', $f)) . '?width=500', 'kk-author-' . $id);
            if ($photo !== '') { $set['photo'] = $photo; $set['bio_source'] = trim(($set['bio_source'] ?? ($m['bio_source'] ?? '')) ?: 'Wikidata') . ' · portrait: Wikimedia Commons'; }
        }
        $cols = implode(', ', array_map(fn($k) => "$k = ?", array_keys($set)));
        $db->prepare("UPDATE kk_author SET $cols, reviewed = '' WHERE author_id = ?")->execute([...array_values($set), $id]);
        return true;
    }

    private static function ensureAuthor(int $id): void
    {
        if (!Library::rows('SELECT 1 AS x FROM kk_author WHERE author_id = ?', [$id])) {
            DB::getInstance()->prepare('INSERT INTO kk_author (author_id) VALUES (?)')->execute([$id]);
        }
    }

    public static function pendingAuthors(int $limit = 4): array
    {
        return Library::rows(
            "SELECT a.author_id FROM mst_author a LEFT JOIN kk_author m ON m.author_id = a.author_id
             WHERE COALESCE(m.wd_tried, '') = '' AND COALESCE(m.reviewed, '') <> 'r' AND EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.author_id = a.author_id)
             ORDER BY a.author_id LIMIT " . max(1, $limit)
        );
    }

    public static function countPendingAuthors(): int
    {
        return (int)(Library::rows(
            "SELECT COUNT(*) AS n FROM mst_author a LEFT JOIN kk_author m ON m.author_id = a.author_id
             WHERE COALESCE(m.wd_tried, '') = '' AND COALESCE(m.reviewed, '') <> 'r' AND EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.author_id = a.author_id)"
        )[0]['n'] ?? 0);
    }

    // ----- Books -----

    /** Look one book up by ISBN, then OCLC, then title (a title match must agree on ISBN or OCLC). */
    public static function book(int $id): bool
    {
        $b = Library::rows('SELECT title, isbn_issn FROM biblio WHERE biblio_id = ?', [$id])[0] ?? null;
        if (!$b) return false;
        $m = Books::meta($id);
        $db = DB::getInstance();
        $now = date('Y-m-d');
        $isbn = self::digits((string)$b['isbn_issn']);
        $oclc = preg_replace('/\D/', '', (string)($m['oclc'] ?? ''));
        $cands = [];
        if (strlen($isbn) === 13) $cands = self::statement('P212', $isbn);
        elseif (strlen($isbn) === 10) $cands = self::statement('P957', $isbn);
        if (!$cands && $oclc !== '') $cands = self::statement('P243', $oclc);
        $byId = (bool)$cands;
        if (!$cands && ($isbn !== '' || $oclc !== '')) {
            foreach (self::byText(trim((string)$b['title'])) as $c) $cands[] = $c;
        }
        $hit = null;
        foreach (array_slice($cands, 0, 4) as $c) {
            $it = self::item($c);
            if (!$it) continue;
            if (!$byId) {
                $has = array_map([self::class, 'digits'], array_merge(self::strings($it, 'P212'), self::strings($it, 'P957')));
                $oc = self::strings($it, 'P243');
                if (!(($isbn !== '' && in_array($isbn, $has, true)) || ($oclc !== '' && in_array($oclc, $oc, true)))) continue;
            }
            $hit = [$c, $it]; break;
        }
        if (!Library::rows('SELECT 1 AS x FROM kk_book WHERE biblio_id = ?', [$id])) {
            $db->prepare('INSERT INTO kk_book (biblio_id) VALUES (?)')->execute([$id]);
        }
        if (!$hit) {
            $db->prepare('UPDATE kk_book SET wd_tried = ? WHERE biblio_id = ?')->execute([$now, $id]);
            return false;
        }
        [$q, $it] = $hit;
        $set = ['wd_id' => $q, 'wd_tried' => $now];
        if (($m['alt_title'] ?? '') === '') {
            $orig = self::strings($it, 'P1476')[0] ?? '';
            if ($orig !== '' && mb_strtolower($orig) !== mb_strtolower(trim((string)$b['title']))) $set['alt_title'] = mb_substr($orig, 0, 300);
        }
        if (($m['oclc'] ?? '') === '' && ($o = self::strings($it, 'P243')[0] ?? '') !== '') $set['oclc'] = mb_substr($o, 0, 30);
        $cols = implode(', ', array_map(fn($k) => "$k = ?", array_keys($set)));
        $db->prepare("UPDATE kk_book SET $cols WHERE biblio_id = ?")->execute([...array_values($set), $id]);
        return true;
    }

    public static function pendingBooks(int $limit = 4): array
    {
        return Library::rows(
            "SELECT b.biblio_id FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE COALESCE(k.wd_tried, '') = '' AND ((b.isbn_issn IS NOT NULL AND b.isbn_issn <> '') OR COALESCE(k.oclc, '') <> '')
             ORDER BY b.biblio_id LIMIT " . max(1, $limit)
        );
    }

    public static function countPendingBooks(): int
    {
        return (int)(Library::rows(
            "SELECT COUNT(*) AS n FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE COALESCE(k.wd_tried, '') = '' AND ((b.isbn_issn IS NOT NULL AND b.isbn_issn <> '') OR COALESCE(k.oclc, '') <> '')"
        )[0]['n'] ?? 0);
    }

    public static function resetTried(): void
    {
        $db = DB::getInstance();
        $db->query("UPDATE kk_author SET wd_tried = '' WHERE wd_id = ''");
        $db->query("UPDATE kk_book SET wd_tried = '' WHERE wd_id = ''");
    }
}
