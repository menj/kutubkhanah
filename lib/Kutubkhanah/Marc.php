<?php
/**
 * Kutubkhanah: read a MARC 21 (MARCXML) record from an SRU response into Kutubkhanah's lookup fields.
 * Used for the Library of Congress, K10plus and any other SRU catalogue that returns MARCXML.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Marc
{
    const NS = 'http://www.loc.gov/MARC21/slim';

    /** First MARC record in an SRU searchRetrieve response (or a bare MARCXML document), as lookup fields. */
    public static function fromSru(string $xml): ?array
    {
        if ($xml === '' || stripos($xml, 'record') === false) return null;
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) return null;

        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('m', self::NS);
        $record = $xp->query('//m:record')->item(0);
        if (!$record) return null;
        return self::read($xp, $record);
    }

    private static function read(\DOMXPath $xp, \DOMElement $rec): ?array
    {
        $fields = [];   // tag => list of ['ind1','ind2','sub' => [[code, value], ...]]
        $control = [];
        foreach ($xp->query('m:controlfield', $rec) as $cf) {
            $control[$cf->getAttribute('tag')] = $cf->textContent;
        }
        foreach ($xp->query('m:datafield', $rec) as $df) {
            $subs = [];
            foreach ($xp->query('m:subfield', $df) as $sf) $subs[] = [$sf->getAttribute('code'), trim($sf->textContent)];
            $fields[$df->getAttribute('tag')][] = ['ind1' => $df->getAttribute('ind1'), 'ind2' => $df->getAttribute('ind2'), 'sub' => $subs];
        }

        $first = fn(string $tag) => $fields[$tag][0] ?? null;
        $sub = function (?array $f, string $codes): string {
            if (!$f) return '';
            $out = [];
            foreach ($f['sub'] as [$c, $v]) if (str_contains($codes, $c)) $out[] = $v;
            return trim(implode(' ', $out));
        };

        $t = $first('245');
        $title = self::clean($sub($t, 'a'));
        if ($title === '') return null;
        $subtitle = self::clean($sub($t, 'b'));
        $part = self::clean($sub($t, 'np'));
        if ($part !== '') $title .= '. ' . $part;
        if ($subtitle !== '') $title .= ': ' . $subtitle;

        // Authors: main entry plus added personal names (translators and editors are kept; they are real contributors).
        $authors = [];
        foreach (['100', '110', '700', '710'] as $tag) {
            foreach ($fields[$tag] ?? [] as $f) {
                $name = self::name($sub($f, 'a') . ($tag[1] === '1' && $sub($f, 'b') !== '' ? ' ' . $sub($f, 'b') : ''), $tag[1] === '0' ? $f['ind1'] : '');
                if ($name !== '' && !in_array($name, $authors, true)) $authors[] = $name;
            }
        }

        // Publication: RDA 264 (second indicator 1 = publication) or AACR2 260.
        $pub = null;
        foreach ($fields['264'] ?? [] as $f) if ($f['ind2'] === '1') { $pub = $f; break; }
        $pub ??= $first('260') ?? $first('264');
        $place = self::clean(self::firstSub($pub, 'a'));
        $publisher = self::clean(self::firstSub($pub, 'b'));
        $date = self::clean(self::firstSub($pub, 'c'));
        if ($date === '' && isset($control['008']) && preg_match('/^\d{4}$/', substr($control['008'], 7, 4))) $date = substr($control['008'], 7, 4);

        $extent = self::clean($sub($first('300'), 'a'));
        $pages = '';
        if (preg_match_all('/(\d+)\s*(?:p\b|pages|pp|hlm|halaman|S\.|ص)/iu', $extent, $m)) $pages = (string)max(array_map('intval', $m[1]));
        elseif (preg_match_all('/\d+/', $extent, $m)) $pages = (string)max(array_map('intval', $m[0]));

        $lang = '';
        if (isset($control['008']) && strlen($control['008']) >= 38) $lang = trim(substr($control['008'], 35, 3));
        if (($lang === '' || $lang === '|||' || $lang === 'und') && ($l = self::firstSub($first('041'), 'a')) !== '') $lang = substr($l, 0, 3);

        $series = '';
        $s = $first('830') ?? $first('490') ?? $first('440');
        if ($s) {
            $series = self::clean($sub($s, 'a'));
            $vol = self::clean($sub($s, 'v'));
            if ($vol !== '') $series .= ' ; ' . $vol;
        }

        $subjects = [];
        foreach (['600', '610', '611', '630', '650', '651', '655'] as $tag) {
            foreach ($fields[$tag] ?? [] as $f) {
                $parts = [];
                foreach ($f['sub'] as [$c, $v]) if (str_contains('abcdtvxyz', $c)) $parts[] = self::clean($v);
                $label = trim(implode(' -- ', array_filter($parts)));
                if ($label !== '' && !in_array($label, $subjects, true)) $subjects[] = $label;
            }
        }

        $oclc = '';
        foreach ($fields['035'] ?? [] as $f) {
            if (preg_match('/\(OCoLC\)\D*(\d+)/', self::firstSub($f, 'a'), $m)) { $oclc = $m[1]; break; }
        }

        // Original-script title (MARC 880 linked to 245), e.g. Arabic or Jawi.
        $alt = '';
        foreach ($fields['880'] ?? [] as $f) {
            if (str_starts_with(self::firstSub($f, '6'), '245')) {
                $alt = self::clean(trim($sub($f, 'a') . ($sub($f, 'b') !== '' ? ': ' . self::clean($sub($f, 'b')) : '')));
                break;
            }
        }

        $dewey = self::clean(self::firstSub($first('082'), 'a'));
        $dewey = str_replace(['/', "'"], '', $dewey);

        return [
            'title'        => $title,
            'authors'      => $authors,
            'publisher'    => $publisher,
            'place'        => $place,
            'publish_date' => $date,
            'edition'      => self::clean($sub($first('250'), 'a')),
            'pages'        => $pages,
            'collation'    => $extent,
            'language'     => Lookup::language($lang),
            'description'  => trim($sub($first('520'), 'a')),
            'subjects'     => $subjects,
            'dewey'        => $dewey,
            'lcc'          => self::clean(trim($sub($first('050'), 'a') . ' ' . $sub($first('050'), 'b'))),
            'lccn'         => preg_replace('/\s+/', '', $sub($first('010'), 'a')),
            'oclc'         => $oclc,
            'series'       => $series,
            'alt_title'    => $alt,
        ];
    }

    private static function firstSub(?array $f, string $code): string
    {
        if (!$f) return '';
        foreach ($f['sub'] as [$c, $v]) if ($c === $code) return $v;
        return '';
    }

    /** Strip ISBD punctuation and brackets that cataloguers add at the end of subfields. */
    public static function clean(string $s, bool $brackets = true): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        $s = preg_replace('/[\s\/:;,=+]+$/u', '', $s);
        // Drop a closing full stop, but keep initials ("M.") and abbreviations ("p.", "ed.", "Inc.").
        if (str_ends_with($s, '.') && !preg_match('/(\b\p{Lu}|\b(?:p|pp|S|ed|eds|Inc|Ltd|Co|Jr|Sr|St|cm|vol|no|hlm|al|etc|ca|rev|repr|trans|comp))\.$/u', $s)) {
            $s = rtrim(substr($s, 0, -1));
        }
        if ($brackets) $s = trim($s, " []");
        return trim(preg_replace('/[\s\/:;,=+]+$/u', '', $s));
    }

    /** "Dostoyevsky, Fyodor, 1821-1881." → "Fyodor Dostoyevsky"; forename-first names are kept as they are. */
    public static function name(string $raw, string $ind1 = '1'): string
    {
        $raw = self::clean($raw);
        $raw = preg_replace('/,?\s*(\d{3,4}\??-(\d{3,4}\??)?|b\.\s*\d{3,4}|d\.\s*\d{3,4}|active.*)$/u', '', $raw);
        $raw = self::clean($raw);
        if ($ind1 === '1' && substr_count($raw, ',') >= 1) {
            [$last, $rest] = array_map('trim', explode(',', $raw, 2));
            $rest = trim(preg_replace('/,.*$/', '', $rest));
            if ($rest !== '') return $rest . ' ' . $last;
            return $last;
        }
        return $raw;
    }
}
