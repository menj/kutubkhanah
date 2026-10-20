<?php
/**
 * Kutubkhanah: public-site data and the shared book card
 * (home page, author pages, "More by this author").
 * Only books visible in the OPAC (not opac_hide, not needing details) are ever returned.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Library
{
    const VISIBLE = "b.opac_hide = 0 AND COALESCE(k.needs_details, 0) = 0";

    public static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    public static function rows(string $sql, array $args = []): array
    {
        $st = DB::getInstance()->prepare($sql);
        if (!$st || !$st->execute($args)) return [];
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function value(string $sql, array $args = []): int
    {
        $st = DB::getInstance()->prepare($sql);
        if (!$st || !$st->execute($args)) return 0;
        return (int)$st->fetchColumn();
    }

    public static function stats(): array
    {
        $base = 'FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id WHERE ' . self::VISIBLE;
        return [
            'books'   => self::value('SELECT COUNT(*) ' . $base),
            'digital' => self::value('SELECT COUNT(*) ' . $base . " AND (k.format = 'Digital Archive' OR k.digital_link <> '')"),
            'genres'  => self::value('SELECT COUNT(DISTINCT bt.topic_id) FROM biblio_topic bt JOIN biblio b ON b.biblio_id = bt.biblio_id
                                      LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id WHERE ' . self::VISIBLE),
            'authors' => self::value('SELECT COUNT(DISTINCT ba.author_id) FROM biblio_author ba JOIN biblio b ON b.biblio_id = ba.biblio_id
                                      LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id WHERE ' . self::VISIBLE),
        ];
    }

    /** Card data for a set of books. $where/$args extend the visibility filter. */
    public static function books(string $where = '1=1', array $args = [], string $order = 'b.input_date DESC, b.biblio_id DESC', int $limit = 12): array
    {
        return self::rows(
            "SELECT b.biblio_id, b.title, b.image, b.publish_year, b.call_number,
                    COALESCE(k.format, '') AS format, COALESCE(k.reading_status, '') AS reading_status,
                    COALESCE(k.volume, '') AS volume, COALESCE(k.rating, 0) AS rating,
                    (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level, a.author_name SEPARATOR ', ')
                       FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) AS authors
             FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE " . self::VISIBLE . " AND ($where)
             ORDER BY $order LIMIT " . max(1, $limit),
            $args
        );
    }

    /** The authors with most books, each with up to four covers and a short line about them, for the home page mosaic. */
    public static function authorMosaics(int $limit = 3): array
    {
        $authors = self::rows(
            "SELECT a.author_id, a.author_name, COUNT(DISTINCT b.biblio_id) AS n
             FROM mst_author a JOIN biblio_author ba ON ba.author_id = a.author_id JOIN biblio b ON b.biblio_id = ba.biblio_id
             LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE " . self::VISIBLE . "
             GROUP BY a.author_id, a.author_name ORDER BY n DESC, a.author_name LIMIT " . max(1, $limit)
        );
        foreach ($authors as &$a) {
            $a['books'] = self::books(
                'EXISTS (SELECT 1 FROM biblio_author x WHERE x.biblio_id = b.biblio_id AND x.author_id = ?)', [(int)$a['author_id']],
                "CASE WHEN b.image IS NULL OR b.image = '' THEN 1 ELSE 0 END, b.publish_year DESC", 4
            );
            $m = self::rows('SELECT nationality, discipline, notes, notes_public, photo, bio FROM kk_author WHERE author_id = ?', [(int)$a['author_id']])[0] ?? [];
            $a['photo'] = (string)($m['photo'] ?? '');
            $bio = !empty($m['notes_public']) && trim((string)($m['notes'] ?? '')) !== '' ? trim($m['notes'])
                : (trim((string)($m['bio'] ?? '')) !== '' ? trim($m['bio'])
                : implode(' · ', array_filter([trim((string)($m['nationality'] ?? '')), trim((string)($m['discipline'] ?? ''))])));
            $a['bio'] = $bio;
        }
        return $authors;
    }

    public static function authorMosaic(array $a): string
    {
        $e = [self::class, 'e'];
        $url = SWB . 'index.php?p=author&amp;id=' . (int)$a['author_id'];
        $photo = (string)($a['photo'] ?? '');
        $books = array_slice($a['books'], 0, $photo !== '' ? 2 : 3);
        $out = '<article class="kk-mosaic' . ($photo !== '' ? ' kk-mosaic--portrait' : '') . '"><a class="kk-mosaic__covers kk-mosaic__covers--' . min(3, count($books) + ($photo !== '' ? 1 : 0)) . '" href="' . $url . '" tabindex="-1" aria-hidden="true">';
        if ($photo !== '') $out .= '<span class="kk-mosaic__photo"><img src="' . Authors::photoUrl($photo, 300) . '" alt="" loading="lazy" decoding="async"></span>';
        foreach ($books as $b) {
            if (!empty($b['image'])) $out .= '<span><img src="' . self::cover($b['image'], 200) . '" alt="" loading="lazy" decoding="async"></span>';
            else $out .= '<span class="kk-mosaic__ph">' . $e(mb_strimwidth($b['title'], 0, 40, '…')) . '</span>';
        }
        $out .= '</a><h3 class="kk-mosaic__name"><a href="' . $url . '">' . $e($a['author_name']) . '</a></h3>'
            . '<p class="kk-mosaic__n">' . $e(sprintf((int)$a['n'] === 1 ? __('%d book') : __('%d books'), (int)$a['n'])) . '</p>';
        if ($a['bio'] !== '') $out .= '<p class="kk-mosaic__bio">' . $e(mb_strimwidth($a['bio'], 0, 140, '…')) . '</p>';
        return $out . '</article>';
    }

    public static function languages(): array
    {
        return self::rows("SELECT DISTINCT lg.language_id, lg.language_name FROM biblio b JOIN mst_language lg ON lg.language_id = b.language_id WHERE b.opac_hide = 0 ORDER BY lg.language_name");
    }

    public static function genres(int $limit = 16): array
    {
        return self::rows(
            'SELECT t.topic_id, t.topic, COUNT(*) AS n
             FROM biblio_topic bt JOIN mst_topic t ON t.topic_id = bt.topic_id
             JOIN biblio b ON b.biblio_id = bt.biblio_id LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE ' . self::VISIBLE . '
             GROUP BY t.topic_id, t.topic ORDER BY n DESC, t.topic LIMIT ' . max(1, $limit)
        );
    }

    public static function news(int $limit = 3): array
    {
        return self::rows(
            "SELECT content_title, content_path, content_desc, COALESCE(publish_date, DATE(last_update)) AS published
             FROM content WHERE is_news = 1 AND COALESCE(is_draft, 0) = 0
             ORDER BY published DESC, content_id DESC LIMIT " . max(1, $limit)
        );
    }

    public static function readingBadge(string $status): string
    {
        $labels = Settings::readingStatuses();
        if (!isset($labels[$status]) || !Settings::get('show_reading')) return '';
        return '<span class="kk-badge kk-badge--' . self::e($status) . '">' . self::e($labels[$status]) . '</span>';
    }

    /** Star rating (1–5) for visitors, when ratings are shown. */
    public static function stars(int $rating, bool $force = false): string
    {
        if ($rating < 1 || (!$force && !Settings::get('show_ratings'))) return '';
        $rating = min(5, $rating);
        return '<span class="kk-stars" role="img" aria-label="' . self::e(sprintf(__('Rated %d out of 5'), $rating)) . '">'
            . '<span class="kk-stars__on" aria-hidden="true">' . str_repeat('★', $rating) . '</span>'
            . '<span class="kk-stars__off" aria-hidden="true">' . str_repeat('★', 5 - $rating) . '</span></span>';
    }

    public static function cover(string $image, int $width = 300): string
    {
        return SWB . 'lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode($image) . '&amp;width=' . $width;
    }

    /** The Private Library book card: cover (or navy placeholder), format badge, call number, title, author, year, reading badge. */
    public static function card(array $b): string
    {
        $e = [self::class, 'e'];
        $url = SWB . 'index.php?p=show_detail&amp;id=' . (int)$b['biblio_id'];
        $out = '<article class="kk-book">';
        $out .= '<a class="kk-book__cover" href="' . $url . '" tabindex="-1" aria-hidden="true">';
        if (!empty($b['image'])) {
            $out .= '<img src="' . self::cover($b['image']) . '" alt="" loading="lazy" decoding="async">';
        } else {
            $out .= '<span class="kk-book__placeholder"><span class="kk-book__ph-title">' . $e($b['title']) . '</span>'
                . ($b['authors'] ? '<span class="kk-book__ph-author">' . $e($b['authors']) . '</span>' : '') . '</span>';
        }
        if (!empty($b['format'])) $out .= '<span class="kk-badge kk-badge--navy kk-book__format">' . $e($b['format']) . '</span>';
        $out .= '</a>' . self::saveButton((int)$b['biblio_id']) . '<div class="kk-book__info">';
        if (!empty($b['call_number'])) $out .= '<span class="kk-book__call">' . $e($b['call_number']) . '</span>';
        $out .= '<h3 class="kk-book__title"><a href="' . $url . '">' . $e($b['title']) . '</a></h3>';
        if (!empty($b['authors'])) $out .= '<p class="kk-book__author">' . $e($b['authors']) . '</p>';
        $badge = self::readingBadge((string)$b['reading_status']) . self::stars((int)($b['rating'] ?? 0));
        if (!empty($b['show_volume']) && ($b['volume'] ?? '') !== '') {
            $out .= '<span class="kk-book__vol">' . $e(sprintf(__('Volume %s'), $b['volume'])) . '</span>';
        }
        if (!empty($b['publish_year']) || $badge) {
            $out .= '<div class="kk-book__meta"><span class="kk-book__year">' . $e($b['publish_year']) . '</span>' . $badge . '</div>';
        }
        return $out . '</div></article>';
    }

    /** A small chapter heading used to give a page a beginning, a middle and an end. */
    public static function chapter(string $label, string $title = ''): string
    {
        return '<header class="kk-chapter"><span class="kk-label">' . self::e($label) . '</span>' . ($title !== '' ? '<h3 class="kk-chapter__title">' . self::e($title) . '</h3>' : '') . '</header>';
    }

    /** Closing row of next steps, shown at the end of listing pages. */
    public static function nextSteps(string $here = ''): string
    {
        $items = ['shelf' => [__('Browse the catalogue'), 'index.php?p=shelf', true], 'series' => [__('Series'), 'index.php?p=series', true],
                  'lists' => [__('Reading lists'), 'index.php?p=lists', (bool)Settings::get('enable_lists')],
                  'suggest' => [__('Suggest a book'), 'index.php?p=suggest', (bool)Settings::get('enable_suggest')],
                  'contact' => [__('Contact'), 'index.php?p=contact', (bool)Settings::get('enable_contact')]];
        $out = '';
        foreach ($items as $k => [$label, $url, $on]) if ($on && $k !== $here) $out .= '<a class="kk-btn kk-btn--outline kk-btn--sm" href="' . $url . '">' . self::e($label) . '</a>';
        return $out === '' ? '' : '<nav class="kk-nextsteps" aria-label="' . self::e(__('Keep exploring')) . '"><span class="kk-label">' . __('Keep exploring') . '</span><div class="kk-cta">' . $out . '</div></nav>';
    }

    /** Bookmark toggle; the list itself lives in the visitor's browser (theme.js). */
    public static function saveButton(int $id, bool $label = false): string
    {
        if (!Settings::get('enable_borrow')) return '';
        return '<button type="button" class="kk-save' . ($label ? ' kk-save--text' : '') . '" data-kk-save="' . $id . '" aria-pressed="false" aria-label="' . self::e(__('Save this book')) . '">'
            . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z"/></svg>'
            . ($label ? '<span data-save-label data-on="' . self::e(__('Saved')) . '">' . __('Save') . '</span>' : '') . '</button>';
    }

    public static function grid(array $books, string $class = 'kk-grid'): string
    {
        return '<div class="' . $class . '">' . implode('', array_map([self::class, 'card'], $books)) . '</div>';
    }

    /* ---------- Brand (KK mark, favicons): files in images/kutubkhanah/ ---------- */

    /** Favicon, touch icon, manifest and theme colour tags. A custom web icon set in System settings wins. */
    public static function iconLinks(): string
    {
        global $sysconf;
        $base = SWB . 'images/kutubkhanah/';
        $custom = $sysconf['webicon'] ?? '';
        if ($custom !== '' && \SLiMS\Filesystems\Storage::images()->isExists('default/' . $custom)) {
            $url = SWB . 'lib/minigalnano/createthumb.php?filename=images/default/' . rawurlencode($custom) . '&width=130';
            return '<link rel="icon" href="' . self::e($url) . '"><link rel="shortcut icon" href="' . self::e($url) . '">';
        }
        return '<link rel="icon" href="' . SWB . 'webicon.ico" sizes="48x48">'
            . '<link rel="icon" href="' . $base . 'favicon.svg" type="image/svg+xml">'
            . '<link rel="icon" href="' . $base . 'favicon-32.png" type="image/png" sizes="32x32">'
            . '<link rel="apple-touch-icon" href="' . $base . 'apple-touch-icon.png">'
            . '<link rel="manifest" href="' . $base . 'site.webmanifest">';
    }

    /** Absolute (scheme-relative) URL of the social sharing image. */
    public static function shareImage(): string
    {
        return '//' . ($_SERVER['SERVER_NAME'] ?? '') . SWB . 'images/kutubkhanah/kk-og.png';
    }

    /** @font-face rules for optional licensed fonts, only for files actually present in fonts/kutubkhanah/private/. */
    /**
     * @font-face rules for the licensed fonts in fonts/kutubkhanah/private/ (Sabon Next LT, Kagnue, Ferments).
     * They ship in the deployment package but not in the public repository; each rule is printed only when its file exists.
     */
    public static function privateFontFaces(): string
    {
        $dir = SB . 'fonts' . DS . 'kutubkhanah' . DS . 'private' . DS;
        $faces = [
            // family,           file base,                weight, style
            ['Sabon Next LT',  'SabonNextLT-Regular',    400, 'normal'],
            ['Sabon Next LT',  'SabonNextLT-Italic',     400, 'italic'],
            ['Sabon Next LT',  'SabonNextLT-Bold',       700, 'normal'],
            ['Sabon Next LT',  'SabonNextLT-BoldItalic', 700, 'italic'],
            ['Kagnue',         'Kagnue-Regular',         400, 'normal'],
            ['Kagnue Outline', 'Kagnue-Outline',         400, 'normal'],
            ['Ferments',       'Ferments-Regular',       400, 'normal'],
            ['Ferments',       'Ferments-Slant',         400, 'italic'],
        ];
        $css = '';
        $have = [];
        foreach ($faces as [$family, $file, $weight, $style]) {
            foreach (['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'] as $ext => $format) {
                if (is_file($dir . $file . '.' . $ext)) {
                    // Sabon also covers weights 500-600 and 800+ by range so semibold/extrabold requests map to the nearest cut.
                    $w = $family === 'Sabon Next LT' ? ($weight === 700 ? '600 900' : '100 500') : (string)$weight;
                    $css .= '@font-face{font-family:"' . $family . '";font-weight:' . $w . ';font-style:' . $style . ';font-display:swap;'
                        . 'src:url("' . SWB . 'fonts/kutubkhanah/private/' . $file . '.' . $ext . '") format("' . $format . '")}';
                    $have[$family] = true;
                    break;
                }
            }
        }
        // Ferments is a script face: give the hero tagline enough size to stay readable when it is in use.
        if (isset($have['Ferments'])) {
            $css .= 'body.kk-theme .kk-hero__tagline{font-size:1.75rem;font-style:normal;letter-spacing:.01em}';
        }
        return $css === '' ? '' : '<style>' . $css . '</style>';
    }
}
