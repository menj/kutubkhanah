<?php
/**
 * Kutubkhanah blog: slugs, safe HTML, excerpts and reading time.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

class Text
{
    /** URL slug from any title, including Arabic and Malay ("Iḥyāʼ ʻulūm" → "ihya-ulum"). */
    public static function slug(string $s, int $max = 180): string
    {
        $s = trim(strip_tags($s));
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s);
            if (is_string($t)) $s = $t;
        } else {
            $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s);
        }
        $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($s));
        $s = trim(substr(trim($s, '-'), 0, $max), '-');
        return $s !== '' ? $s : 'post';
    }

    private static ?\HTMLPurifier $purifier = null;

    /** Allowed HTML for post content (what the editor produces, plus figures, tables and video embeds). */
    public static function clean(string $html): string
    {
        if (self::$purifier === null) {
            $c = \HTMLPurifier_Config::createDefault();
            $c->set('Core.Encoding', 'UTF-8');
            $c->set('HTML.Doctype', 'XHTML 1.0 Transitional');
            $c->set('Cache.DefinitionImpl', null);
            $c->set('HTML.DefinitionID', 'kutubkhanah-blog');
            $c->set('HTML.DefinitionRev', 3);
            $c->set('HTML.Allowed', implode(',', [
                'p[class|dir|lang|style]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'sub', 'sup', 'small', 'mark', 'abbr[title]', 'cite', 'q', 'code', 'kbd',
                'a[href|title|target|rel]', 'ul[class|dir]', 'ol[class|dir|start]', 'li[dir]', 'dl', 'dt', 'dd',
                'blockquote[class|dir|cite]', 'h2[id|class|dir|style]', 'h3[id|class|dir|style]', 'h4[id|class|dir|style]', 'h5[dir]', 'h6[dir]',
                'img[src|alt|title|width|height|class]', 'figure[class]', 'figcaption[dir]', 'hr', 'pre[class|dir]',
                'table[class]', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th[colspan|rowspan|scope|style]', 'td[colspan|rowspan|style]',
                'span[class|dir|lang|style]', 'div[class|dir|lang|style]',
                'iframe[src|width|height|title|allowfullscreen|frameborder]',
            ]));
            $c->set('CSS.AllowedProperties', ['text-align', 'color', 'background-color', 'text-decoration', 'padding-left']);
            $c->set('Attr.AllowedFrameTargets', ['_blank']);
            $c->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
            $c->set('Attr.EnableID', true);
            $c->set('HTML.SafeIframe', true);
            $c->set('URI.SafeIframeRegexp', '%^https://(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
            $c->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $c->set('AutoFormat.RemoveEmpty.RemoveNbsp', false);
            if ($def = $c->maybeGetRawHTMLDefinition()) {
                $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
                $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
                $def->addElement('mark', 'Inline', 'Inline', 'Common');
                $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
                $def->addAttribute('blockquote', 'dir', 'Enum#ltr,rtl,auto');
                foreach (['p', 'span', 'div', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'ul', 'ol', 'figcaption', 'pre'] as $el) $def->addAttribute($el, 'dir', 'Enum#ltr,rtl,auto');
            }
            self::$purifier = new \HTMLPurifier($c);
        }
        return trim(self::$purifier->purify($html));
    }

    /** Plain text from HTML (for excerpts, search snippets, meta descriptions). */
    public static function plain(string $html): string
    {
        $html = preg_replace('#<(script|style)\b.*?</\1>#is', '', $html);
        $html = preg_replace('#</(p|div|h[1-6]|li|blockquote|figcaption|tr)>#i', "$0 ", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** First ~55 words, like WordPress's automatic excerpt. */
    public static function excerpt(string $html, int $words = 55): string
    {
        $text = self::plain(preg_replace('/\[[^\]]+\]/', '', $html));
        $parts = preg_split('/\s+/u', $text, $words + 1);
        if (count($parts) > $words) {
            array_pop($parts);
            return implode(' ', $parts) . '…';
        }
        return $text;
    }

    public static function readingMinutes(string $html): int
    {
        $text = self::plain($html);
        $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
        return max(1, (int)round($words / 220));
    }
}
