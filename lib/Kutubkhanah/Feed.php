<?php
/**
 * Kutubkhanah: "New additions" RSS 2.0 feed (index.php?p=kk_rss), after PMB's new-records feed.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Feed
{
    public static function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost') . SWB;
    }

    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function respond(): void
    {
        global $sysconf;
        while (ob_get_level() > 0) ob_end_clean();
        if (!Settings::get('enable_rss')) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not found';
            exit;
        }
        $base = self::baseUrl();
        $books = Library::rows(
            "SELECT b.biblio_id, b.title, b.image, b.publish_year, b.notes, b.input_date, b.isbn_issn,
                    (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level, a.author_name SEPARATOR ', ')
                       FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) AS authors
             FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id
             WHERE " . Library::VISIBLE . " ORDER BY b.input_date DESC, b.biblio_id DESC LIMIT 30"
        );
        $name = (string)($sysconf['library_name'] ?? 'Kutubkhanah');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n<channel>\n"
            . '<title>' . self::x($name . ' · ' . __('New additions')) . "</title>\n"
            . '<link>' . self::x($base) . "</link>\n"
            . '<atom:link href="' . self::x($base . 'index.php?p=kk_rss') . '" rel="self" type="application/rss+xml"/>' . "\n"
            . '<description>' . self::x(__('Books recently added to the collection.')) . "</description>\n"
            . '<language>' . self::x(substr((string)($sysconf['default_lang'] ?? 'en_US'), 0, 2)) . "</language>\n"
            . '<lastBuildDate>' . date(DATE_RSS, $books ? strtotime($books[0]['input_date']) : time()) . "</lastBuildDate>\n";
        foreach ($books as $b) {
            $url = $base . 'index.php?p=show_detail&id=' . (int)$b['biblio_id'];
            $desc = '';
            if ($b['image']) $desc .= '<p><img src="' . self::x($base . 'lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode($b['image']) . '&width=200') . '" alt=""/></p>';
            $line = trim(($b['authors'] ?: '') . ($b['publish_year'] ? ' (' . $b['publish_year'] . ')' : ''));
            if ($line !== '') $desc .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
            if (trim((string)$b['notes']) !== '') $desc .= '<p>' . htmlspecialchars(mb_substr(trim($b['notes']), 0, 600), ENT_QUOTES, 'UTF-8') . '</p>';
            $out .= "<item>\n<title>" . self::x($b['title']) . "</title>\n<link>" . self::x($url) . "</link>\n"
                . '<guid isPermaLink="true">' . self::x($url) . "</guid>\n"
                . ($b['authors'] ? '<dc:creator>' . self::x($b['authors']) . "</dc:creator>\n" : '')
                . '<pubDate>' . date(DATE_RSS, strtotime($b['input_date'])) . "</pubDate>\n"
                . '<description>' . self::x($desc) . "</description>\n</item>\n";
        }
        $out .= "</channel>\n</rss>\n";
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=900');
        echo $out;
        exit;
    }
}
