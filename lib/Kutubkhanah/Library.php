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

    private static function rows(string $sql, array $args = []): array
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
    public static function books(string $where = '1', array $args = [], string $order = 'b.input_date DESC, b.biblio_id DESC', int $limit = 12): array
    {
        return self::rows(
            "SELECT b.biblio_id, b.title, b.image, b.publish_year, b.call_number,
                    COALESCE(k.format, '') AS format, COALESCE(k.reading_status, '') AS reading_status,
                    (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level, a.author_name SEPARATOR ', ')
                       FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) AS authors
             FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE " . self::VISIBLE . " AND ($where)
             ORDER BY $order LIMIT " . max(1, $limit),
            $args
        );
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
        $out .= '</a><div class="kk-book__info">';
        if (!empty($b['call_number'])) $out .= '<span class="kk-book__call">' . $e($b['call_number']) . '</span>';
        $out .= '<h3 class="kk-book__title"><a href="' . $url . '">' . $e($b['title']) . '</a></h3>';
        if (!empty($b['authors'])) $out .= '<p class="kk-book__author">' . $e($b['authors']) . '</p>';
        $badge = self::readingBadge((string)$b['reading_status']);
        if (!empty($b['publish_year']) || $badge) {
            $out .= '<div class="kk-book__meta"><span class="kk-book__year">' . $e($b['publish_year']) . '</span>' . $badge . '</div>';
        }
        return $out . '</div></article>';
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
    public static function privateFontFaces(): string
    {
        $dir = SB . 'fonts' . DS . 'kutubkhanah' . DS . 'private' . DS;
        $css = '';
        foreach (['Kagnue' => 'Kagnue-Regular', 'Ferments' => 'Ferments-Regular'] as $family => $file) {
            foreach (['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'] as $ext => $format) {
                if (is_file($dir . $file . '.' . $ext)) {
                    $css .= '@font-face{font-family:"' . $family . '";font-weight:400;font-style:normal;font-display:swap;src:url("'
                        . SWB . 'fonts/kutubkhanah/private/' . $file . '.' . $ext . '") format("' . $format . '")}';
                    break;
                }
            }
        }
        // Ferments is a script face: give the hero tagline enough size to stay readable when it is in use.
        if (strpos($css, '"Ferments"') !== false) {
            $css .= 'body.kk-theme .kk-hero__tagline{font-size:1.75rem;font-style:normal;letter-spacing:.01em}';
        }
        return $css === '' ? '' : '<style>' . $css . '</style>';
    }
}
