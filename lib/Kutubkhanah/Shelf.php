<?php
/**
 * Kutubkhanah: public A–Z cover shelf for the OPAC.
 * Books flagged opac_hide (including those that still need details) are never shown.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Shelf
{
    private static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    private static function fold(string $s): string
    {
        if (class_exists('\Normalizer')) {
            $s = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($s, \Normalizer::FORM_D));
        }
        return mb_strtolower($s);
    }

    /** Sort key ignores accents and a leading "The", "A" or "An", as Libib does. */
    private static function sortKey(string $title): string
    {
        return preg_replace('/^(the|a|an)\s+/u', '', trim(self::fold(strip_tags($title))));
    }

    private static function letter(string $key): string
    {
        $c = strtoupper(substr($key, 0, 1));
        return ctype_alpha($c) ? $c : '#';
    }

    public static function books(): array
    {
        try {
            return self::query();
        } catch (\PDOException $e) {
            // Database upgraded from plain SLiMS without the Kutubkhanah table: create it and retry once.
            global $dbs;
            Schema::ensure($dbs);
            return self::query();
        }
    }

    private static function query(): array
    {
        $stmt = DB::getInstance()->query(
            "SELECT b.biblio_id, b.title, b.image, b.isbn_issn, b.language_id, COALESCE(lg.language_name, '') AS language_name,
                    COALESCE(k.reading_status, '') AS reading_status, COALESCE(k.format, '') AS format,
                    COALESCE(k.digital_link, '') AS digital_link, COALESCE(k.tags, '') AS tags,
                    (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level, a.author_name SEPARATOR ', ')
                       FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id
                      WHERE ba.biblio_id = b.biblio_id) AS authors,
                    (SELECT GROUP_CONCAT(bt.topic_id) FROM biblio_topic bt WHERE bt.biblio_id = b.biblio_id) AS genre_ids
             FROM biblio b
             LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             LEFT JOIN mst_language lg ON lg.language_id = b.language_id
             WHERE b.opac_hide = 0 AND COALESCE(k.needs_details, 0) = 0"
        );
        // SLiMS runs PDO in silent mode, so a failed query returns false rather than throwing.
        if ($stmt === false) throw new \PDOException('Shelf query failed');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['key'] = self::sortKey($r['title']);
            $r['letter'] = self::letter($r['key']);
        }
        unset($r);
        // Group first ("#" holds digits and non-Latin titles, e.g. Arabic), then natural order within the group.
        usort($rows, fn($a, $b) => [$a['letter'] === '#' ? 0 : 1, $a['letter'], 0] <=> [$b['letter'] === '#' ? 0 : 1, $b['letter'], 0]
            ?: strnatcmp($a['key'], $b['key']));
        return $rows;
    }

    public static function render(): string
    {
        $s = Settings::all();
        $books = self::books();
        $statuses = Settings::readingStatuses();
        $e = [self::class, 'e'];

        ob_start();
        echo '<link rel="stylesheet" href="' . SWB . 'css/kutubkhanah/shelf.css?v=1.1.0">';
        echo '<style>' . Settings::cssVars('.kk-shelf') . '</style>';
        echo '<div class="kk-shelf" data-scheme="' . $e($s['scheme']) . '">';
        if ($s['shelf_title'] !== '') echo '<h2 class="kk-shelf__title">' . $e($s['shelf_title']) . '</h2>';

        if (!$books) {
            echo '<p class="kk-shelf__empty">' . __('No books on the shelf yet.') . '</p></div>';
            return ob_get_clean();
        }

        echo '<div class="kk-shelf__bar">';
        if ($s['show_search']) {
            echo '<label class="kk-shelf__search"><span class="kk-sr">' . __('Search books') . '</span>'
                . '<input type="search" data-kk-search placeholder="' . $e(__('Search title or author')) . '" autocomplete="off"></label>';
        }
        if ($s['show_status']) {
            echo '<label class="kk-shelf__select"><span class="kk-sr">' . __('Reading status') . '</span><select data-kk-status>'
                . '<option value="">' . __('All books') . '</option>';
            foreach ($statuses as $v => $l) echo '<option value="' . $e($v) . '">' . $e($l) . '</option>';
            echo '</select></label>';
        }
        // Genre / format / language filters (Private Library catalogue), only for values that exist.
        $genreNames = [];
        $ids = array_unique(array_filter(explode(',', implode(',', array_column($books, 'genre_ids')))));
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            foreach (DB::getInstance()->query("SELECT topic_id, topic FROM mst_topic WHERE topic_id IN ($in) ORDER BY topic") ?: [] as $t) {
                $genreNames[(int)$t['topic_id']] = $t['topic'];
            }
        }
        $formats = array_values(array_intersect(Settings::formats(), array_unique(array_merge(
            array_column($books, 'format'),
            array_filter(array_column($books, 'digital_link')) ? ['Digital Archive'] : []
        ))));
        $langs = [];
        foreach ($books as $b) if ($b['language_id'] !== null && $b['language_id'] !== '') $langs[$b['language_id']] = $b['language_name'] ?: $b['language_id'];
        asort($langs);
        $selGenre = (int)($_GET['genre'] ?? 0);
        $selFormat = in_array($_GET['format'] ?? '', Settings::formats(), true) ? $_GET['format'] : '';
        $selLang = (string)($_GET['lang'] ?? '');
        // Keep a requested filter even when no book matches yet, so "Digital Archives" shows an empty result, not everything.
        if ($selFormat !== '' && !in_array($selFormat, $formats, true)) $formats[] = $selFormat;
        $select = function (string $attr, string $label, string $all, array $opts, $selected) use ($e) {
            if (count($opts) < 1) return '';
            $h = '<label class="kk-shelf__select"><span class="kk-sr">' . $e($label) . '</span><select ' . $attr . '><option value="">' . $e($all) . '</option>';
            foreach ($opts as $v => $l) $h .= '<option value="' . $e($v) . '"' . ((string)$v === (string)$selected ? ' selected' : '') . '>' . $e($l) . '</option>';
            return $h . '</select></label>';
        };
        echo $select('data-kk-genre', __('Genre'), __('All genres'), $genreNames, $selGenre ?: '');
        echo $select('data-kk-format', __('Format'), __('All formats'), array_combine($formats, $formats) ?: [], $selFormat);
        if (count($langs) > 1) echo $select('data-kk-lang', __('Language'), __('All languages'), $langs, $selLang);
        echo '<span class="kk-shelf__count" data-kk-count data-one="' . $e(__('book')) . '" data-many="' . $e(__('books')) . '" aria-live="polite">'
            . count($books) . ' ' . (count($books) === 1 ? __('book') : __('books')) . '</span></div>';

        $letters = array_values(array_unique(array_column($books, 'letter')));
        $group = $s['group_letters'] && count($letters) > 1;
        if ($group) {
            echo '<nav class="kk-shelf__az" aria-label="' . $e(__('Jump to letter')) . '">';
            foreach ($letters as $l) echo '<a href="#kk-' . ($l === '#' ? '0' : $l) . '">' . $e($l) . '</a>';
            echo '</nav>';
        }

        $current = null;
        $open = false;
        foreach ($books as $b) {
            if ($group && $b['letter'] !== $current) {
                if ($open) echo '</div></section>';
                $current = $b['letter'];
                echo '<section class="kk-shelf__group" id="kk-' . ($current === '#' ? '0' : $current) . '">'
                    . '<h3 class="kk-shelf__letter">' . $e($current) . '</h3><div class="kk-shelf__grid">';
                $open = true;
            } elseif (!$group && !$open) {
                echo '<section class="kk-shelf__group"><div class="kk-shelf__grid">';
                $open = true;
            }
            $search = self::fold($b['title'] . ' ' . $b['authors'] . ' ' . $b['isbn_issn'] . ' ' . $b['tags']);
            $digital = ($b['format'] === 'Digital Archive' || $b['digital_link'] !== '') ? '1' : '';
            echo '<a class="kk-card" href="' . SWB . 'index.php?p=show_detail&amp;id=' . (int)$b['biblio_id'] . '"'
                . ' data-search="' . $e($search) . '" data-status="' . $e($b['reading_status']) . '"'
                . ' data-format="' . $e($b['format']) . '" data-digital="' . $digital . '"'
                . ' data-genres=",' . $e((string)$b['genre_ids']) . ',"' . ' data-lang="' . $e((string)$b['language_id']) . '">';
            echo '<span class="kk-card__cover">';
            if ($b['image']) {
                echo '<img src="' . SWB . 'lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode($b['image']) . '&amp;width=300" alt="" loading="lazy" decoding="async">';
            } else {
                echo '<span class="kk-card__nocover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
                    . '<path d="M3 5.5c3-1 6-1 9 1v13c-3-2-6-2-9-1z"/><path d="M21 5.5c-3-1-6-1-9 1v13c3-2 6-2 9-1z"/></svg>'
                    . $e(__('No cover')) . '</span>';
            }
            echo '</span><span class="kk-card__title">' . $e($b['title']) . '</span>';
            if ($s['show_authors'] && $b['authors']) echo '<span class="kk-card__author">' . $e($b['authors']) . '</span>';
            echo '</a>';
        }
        if ($open) echo '</div></section>';
        echo '<p class="kk-shelf__none" data-kk-none hidden>' . __('No books match your search.') . '</p>';
        echo '</div>';
        echo '<script src="' . SWB . 'js/kutubkhanah/shelf.js?v=1.1.0"></script>';
        return ob_get_clean();
    }
}
