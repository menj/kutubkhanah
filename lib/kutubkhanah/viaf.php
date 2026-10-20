<?php
/**
 * Kutubkhanah: author authority records from VIAF (Virtual International Authority File).
 * Finds the person by name, checks the heading and ISNI, and fills only empty fields: native-script name,
 * birth and death years, ISNI and a VIAF link.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Viaf
{
    private static function words(string $s): array
    {
        return array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)), fn($w) => mb_strlen($w) > 1));
    }

    /** All strings found under a key anywhere in a decoded JSON tree. */
    private static function collect($node, string $key, array &$out): void
    {
        if (!is_array($node)) return;
        foreach ($node as $k => $v) {
            if ($k === $key && is_string($v)) $out[] = $v;
            elseif (is_array($v)) self::collect($v, $key, $out);
        }
    }

    private static function year(string $d): string
    {
        return preg_match('/^(\d{3,4})/', trim($d), $m) ? $m[1] : '';
    }

    public static function author(int $id): bool
    {
        $rows = Library::rows('SELECT author_name FROM mst_author WHERE author_id = ?', [$id]);
        if (!$rows) return false;
        $m = Authors::meta($id);
        if (($m['reviewed'] ?? '') === 'r') return false;
        $name = trim((string)($m['search_name'] ?? ''));
        if ($name === '') {
            $name = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', (string)$rows[0]['author_name']));
            if (!preg_match('/[\x{0600}-\x{06FF}\x{3400}-\x{9FFF}]/u', $name) && preg_match('/^([^,]+),\s*(.+)$/', $name, $mm)) $name = trim($mm[2] . ' ' . $mm[1]);
        }
        $viaf = preg_replace('/\D/', '', (string)($m['viaf_id'] ?? ''));
        if ($viaf === '') {
            $d = Http::json('https://viaf.org/viaf/AutoSuggest?query=' . rawurlencode($name));
            $want = self::words($name);
            foreach ($d['result'] ?? [] as $r) {
                if (($r['nametype'] ?? '') !== 'personal' || empty($r['viafid'])) continue;
                $have = self::words((string)($r['term'] ?? '') . ' ' . (string)($r['displayForm'] ?? ''));
                if ($want && count(array_intersect($want, $have)) >= min(2, count($want))) { $viaf = preg_replace('/\D/', '', (string)$r['viafid']); break; }
            }
        }
        $db = DB::getInstance();
        if (!Library::rows('SELECT 1 AS x FROM kk_author WHERE author_id = ?', [$id])) {
            $db->prepare('INSERT INTO kk_author (author_id) VALUES (?)')->execute([$id]);
        }
        if ($viaf === '') return false;
        $rec = Http::json('https://viaf.org/viaf/' . $viaf . '/viaf.json');
        if (!$rec) return false;
        // When an ISNI is known it must agree, so a namesake is never filled in.
        $isniKnown = preg_replace('/\D/', '', (string)($m['isni'] ?? ''));
        $ids = [];
        self::collect($rec, '#text', $ids); self::collect($rec, '@nsid', $ids); self::collect($rec, 'text', $ids);
        $found = '';
        foreach ($ids as $s) if (preg_match('/ISNI\|?\s*([0-9 ]{16,19})/', $s, $im) || preg_match('/^(\d{16})$/', trim($s), $im)) { $found = preg_replace('/\D/', '', $im[1]); break; }
        if ($isniKnown !== '' && $found !== '' && $isniKnown !== $found) return false;

        $set = ['viaf_id' => $viaf];
        if (($m['native_name'] ?? '') === '') {
            foreach ($ids as $s) if (preg_match('/[\x{0600}-\x{06FF}\x{3400}-\x{9FFF}]/u', $s) && mb_strlen($s) <= 100) { $set['native_name'] = trim($s, " ,.;"); break; }
        }
        $b = []; self::collect($rec, 'birthDate', $b); $dd = []; self::collect($rec, 'deathDate', $dd);
        if (($m['birth_year'] ?? '') === '' && $b && ($y = self::year($b[0])) !== '') $set['birth_year'] = $y;
        if (($m['death_year'] ?? '') === '' && $dd && ($y = self::year($dd[0])) !== '') $set['death_year'] = $y;
        if (($m['isni'] ?? '') === '' && strlen($found) === 16) $set['isni'] = trim(chunk_split($found, 4, ' '));
        $cols = implode(', ', array_map(fn($k) => "$k = ?", array_keys($set)));
        $db->prepare("UPDATE kk_author SET $cols WHERE author_id = ?")->execute([...array_values($set), $id]);
        return true;
    }
}
