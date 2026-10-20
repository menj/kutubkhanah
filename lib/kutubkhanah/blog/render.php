<?php
/**
 * Kutubkhanah blog: public pages (index, category, tag, month, search, single post, static page),
 * shortcodes, comments, the home-page section and the RSS feed.
 *
 * Shortcodes: [book id="12"], [books ids="12,40,7" title="Further reading"], [series name="Discworld"],
 * and WordPress's [caption] and [embed] for imported posts.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Messages;
use SLiMS\Kutubkhanah\Series;
use SLiMS\Kutubkhanah\Settings;

class Render
{
    private static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    public static function title(): string
    {
        return (string)(Settings::get('blog_title') ?: __('Journal'));
    }

    /* ---------- Shortcodes ---------- */

    /** Book ids mentioned through [book] / [books] shortcodes. */
    public static function bookIds(string $content): array
    {
        $ids = [];
        if (preg_match_all('/' . Wp::shortcodeRegex(['book', 'books']) . '/', $content, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if ($m[1] === '[' && $m[6] === ']') continue;
                $a = Wp::shortcodeParseAtts($m[3]);
                foreach (preg_split('/[\s,]+/', (string)($a['id'] ?? $a['ids'] ?? '')) as $id) if ((int)$id > 0) $ids[] = (int)$id;
            }
        }
        return array_values(array_unique($ids));
    }

    private static function bookCards(array $ids, string $class = 'kk-postbooks'): string
    {
        if (!$ids) return '';
        $rows = Library::books('b.biblio_id IN (' . implode(',', array_map('intval', $ids)) . ')', [], 'b.title', count($ids));
        $by = [];
        foreach ($rows as $r) $by[(int)$r['biblio_id']] = $r;
        $out = '';
        foreach ($ids as $id) {
            if (!isset($by[$id])) continue;
            $b = $by[$id];
            $url = SWB . 'index.php?p=show_detail&amp;id=' . $id;
            $cover = $b['image'] ? '<img src="' . Library::cover($b['image'], 160) . '" alt="" loading="lazy">' : '<span class="kk-postbook__ph" aria-hidden="true"></span>';
            $out .= '<a class="kk-postbook" href="' . $url . '"><span class="kk-postbook__cover">' . $cover . '</span><span class="kk-postbook__body">'
                . '<span class="kk-postbook__eyebrow">' . __('In the collection') . '</span>'
                . '<strong class="kk-postbook__title" dir="auto">' . self::e($b['title']) . '</strong>'
                . ($b['authors'] ? '<span class="kk-postbook__author" dir="auto">' . self::e($b['authors']) . '</span>' : '')
                . '<span class="kk-postbook__meta">' . self::e($b['publish_year']) . ' ' . Library::stars((int)$b['rating']) . '</span>'
                . '</span></a>';
        }
        return $out ? '<div class="' . $class . '">' . $out . '</div>' : '';
    }

    public static function content(string $html): string
    {
        $tags = ['book', 'books', 'series', 'caption', 'wp_caption', 'embed'];
        $html = preg_replace_callback('/' . Wp::shortcodeRegex($tags) . '/', function ($m) {
            if ($m[1] === '[' && $m[6] === ']') return substr($m[0], 1, -1);
            $a = Wp::shortcodeParseAtts($m[3]);
            $inner = $m[5] ?? '';
            switch ($m[2]) {
                case 'book':
                    return self::bookCards([(int)($a['id'] ?? $a[0] ?? 0)]);
                case 'books':
                    $ids = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', (string)($a['ids'] ?? '')))));
                    $title = trim((string)($a['title'] ?? ''));
                    return ($title !== '' ? '<p class="kk-postbooks__title">' . self::e($title) . '</p>' : '') . self::bookCards($ids, 'kk-postbooks kk-postbooks--grid');
                case 'series':
                    $name = trim((string)($a['name'] ?? ''));
                    return $name !== '' ? '<p><a class="kk-btn kk-btn--outline kk-btn--sm" href="' . self::e(Series::url($name)) . '">' . self::e(sprintf(__('The %s series in the collection'), $name)) . '</a></p>' : '';
                case 'caption':
                case 'wp_caption':
                    // WordPress: [caption id=".." align="alignleft" width=".."]<img ...> Caption text[/caption]
                    $img = preg_match('#<a[^>]*>\s*<img[^>]*>\s*</a>|<img[^>]*>#i', $inner, $im) ? $im[0] : '';
                    $cap = trim($a['caption'] ?? trim(str_replace($img, '', $inner)));
                    $align = preg_replace('/[^a-z]/', '', (string)($a['align'] ?? ''));
                    return '<figure class="wp-caption ' . self::e($align) . '">' . $img . ($cap !== '' ? '<figcaption>' . $cap . '</figcaption>' : '') . '</figure>';
                case 'embed':
                    return self::embed(trim(strip_tags($inner)));
            }
            return $m[0];
        }, $html);
        // A bare YouTube/Vimeo link on its own line becomes a player (as in WordPress).
        $html = preg_replace_callback('#<p>\s*(https?://[^\s<]+)\s*</p>#i', fn($m) => ($e = self::embed($m[1])) !== '' ? $e : $m[0], $html);
        // Lazy images and scrollable tables.
        $html = preg_replace('#<img(?![^>]*\bloading=)#i', '<img loading="lazy" decoding="async"', $html);
        $html = preg_replace('#<table#i', '<div class="kk-table-wrap"><table', $html);
        $html = preg_replace('#</table>#i', '</table></div>', $html);
        return $html;
    }

    public static function embed(string $url): string
    {
        $src = '';
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,20})#', $url, $m)) $src = 'https://www.youtube-nocookie.com/embed/' . $m[1];
        elseif (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) $src = 'https://player.vimeo.com/video/' . $m[1];
        return $src ? '<div class="kk-embed"><iframe src="' . self::e($src) . '" title="' . self::e(__('Video')) . '" loading="lazy" allowfullscreen></iframe></div>' : '';
    }

    /* ---------- Pieces ---------- */

    public static function date(?string $dt): string
    {
        return $dt ? date('j F Y', strtotime($dt)) : '';
    }

    private static function featured(array $p, int $width, string $class): string
    {
        $m = Media::get((int)$p['featured_media']);
        if (!$m) return '';
        return '<img class="' . $class . '" src="' . self::e(Media::url($m, $width)) . '" alt="' . self::e($m['alt']) . '" loading="lazy" decoding="async"'
            . ' width="' . (int)$m['width'] . '" height="' . (int)$m['height'] . '">';
    }

    public static function card(array $p, bool $lead = false): string
    {
        $url = self::e(Posts::url($p));
        $terms = Terms::forPost((int)$p['id']);
        $cat = $terms['category'][0] ?? null;
        $excerpt = $p['excerpt'] !== '' && $p['excerpt'] !== null ? $p['excerpt'] : Text::excerpt((string)$p['content'], $lead ? 60 : 32);
        $img = self::featured($p, $lead ? 1200 : 640, 'kk-post-card__img');
        return '<article class="kk-post-card' . ($lead ? ' kk-post-card--lead' : '') . ($img ? '' : ' kk-post-card--noimg') . '">'
            . ($img ? '<a class="kk-post-card__media" href="' . $url . '" tabindex="-1" aria-hidden="true">' . $img . '</a>' : '')
            . '<div class="kk-post-card__body">'
            . ($cat ? '<a class="kk-post-card__cat" href="' . self::e(Terms::url($cat)) . '">' . self::e($cat['name']) . '</a>' : '')
            . ($p['sticky'] ? '<span class="kk-post-card__pin">' . __('Featured') . '</span>' : '')
            . '<h2 class="kk-post-card__title"><a href="' . $url . '" dir="auto">' . self::e($p['title']) . '</a></h2>'
            . '<p class="kk-post-card__excerpt" dir="auto">' . self::e($excerpt) . '</p>'
            . '<p class="kk-post-card__meta"><time datetime="' . self::e(date('c', strtotime((string)$p['published_at']))) . '">' . self::e(self::date($p['published_at'])) . '</time>'
            . ' · ' . self::e(sprintf(__('%d min read'), Text::readingMinutes((string)$p['content'])))
            . ((int)$p['comment_count'] > 0 ? ' · ' . self::e(sprintf((int)$p['comment_count'] === 1 ? __('%d comment') : __('%d comments'), (int)$p['comment_count'])) : '')
            . '</p></div></article>';
    }

    private static function sidebar(string $q = ''): string
    {
        $out = '<aside class="kk-blog-side" aria-label="' . self::e(self::title()) . '">';
        $out .= '<form class="kk-blog-search" role="search" method="get" action="index.php"><input type="hidden" name="p" value="blog">'
            . '<label class="kk-sr" for="kk-blog-s">' . __('Search the journal') . '</label>'
            . '<input class="kk-input" id="kk-blog-s" name="s" type="search" value="' . self::e($q) . '" placeholder="' . self::e(__('Search the journal')) . '">'
            . '<button class="kk-btn kk-btn--primary kk-btn--sm" type="submit">' . __('Search') . '</button></form>';
        $cats = array_filter(Terms::tree(), fn($t) => (int)$t['n'] > 0);
        if ($cats) {
            $out .= '<section class="kk-blog-side__box"><h3>' . __('Categories') . '</h3><ul class="kk-blog-side__list">';
            foreach ($cats as $t) {
                $out .= '<li style="--depth:' . (int)$t['depth'] . '"><a href="' . self::e(Terms::url($t)) . '">' . self::e($t['name']) . '</a> <span>' . (int)$t['n'] . '</span></li>';
            }
            $out .= '</ul></section>';
        }
        $tags = array_filter(Terms::all('tag'), fn($t) => (int)$t['n'] > 0);
        if ($tags) {
            usort($tags, fn($a, $b) => $b['n'] <=> $a['n']);
            $out .= '<section class="kk-blog-side__box"><h3>' . __('Tags') . '</h3><p class="kk-blog-tags">';
            foreach (array_slice($tags, 0, 30) as $t) $out .= '<a href="' . self::e(Terms::url($t)) . '">#' . self::e($t['name']) . '</a> ';
            $out .= '</p></section>';
        }
        if ($arch = Posts::archives()) {
            $out .= '<section class="kk-blog-side__box"><h3>' . __('Archives') . '</h3><ul class="kk-blog-side__list">';
            foreach ($arch as $a) {
                $out .= '<li><a href="index.php?p=blog&amp;month=' . self::e($a['ym']) . '">' . self::e(date('F Y', strtotime($a['ym'] . '-01'))) . '</a> <span>' . (int)$a['n'] . '</span></li>';
            }
            $out .= '</ul></section>';
        }
        if (Settings::get('enable_rss')) $out .= '<p class="kk-blog-side__rss"><a href="index.php?p=blog-rss">' . __('Subscribe (RSS)') . '</a></p>';
        return $out . '</aside>';
    }

    private static function pager(int $page, int $pages, array $params): string
    {
        if ($pages <= 1) return '';
        $link = fn($n) => 'index.php?' . self::e(http_build_query(array_merge(['p' => 'blog'], $params, ['page' => $n])));
        $out = '<nav class="kk-pager-nav" aria-label="' . __('Pages') . '">';
        if ($page > 1) $out .= '<a rel="prev" href="' . $link($page - 1) . '">← ' . __('Newer posts') . '</a>';
        $out .= '<span>' . self::e(sprintf(__('Page %1$d of %2$d'), $page, $pages)) . '</span>';
        if ($page < $pages) $out .= '<a rel="next" href="' . $link($page + 1) . '">' . __('Older posts') . ' →</a>';
        return $out . '</nav>';
    }

    /* ---------- Pages ---------- */

    /** Listing pages. Returns [title, html, status code]. */
    public static function index(array $get): array
    {
        $per = max(3, (int)Settings::get('blog_per_page'));
        $page = max(1, (int)($get['page'] ?? 1));
        $f = ['type' => 'post', 'status' => 'public', 'page' => $page, 'per_page' => $per];
        $params = [];
        $title = self::title();
        $intro = '';
        if (!empty($get['category']) || !empty($get['tag'])) {
            $tax = !empty($get['category']) ? 'category' : 'tag';
            $t = Terms::bySlug($tax, (string)$get[$tax]);
            if (!$t) return [__('Not found'), '<p class="kk-muted">' . __('Nothing here.') . '</p>', 404];
            $f['term'] = (int)$t['id'];
            $params[$tax] = $t['slug'];
            $title = ($tax === 'tag' ? '#' : '') . $t['name'];
            if ($t['description'] !== '') $intro = '<p class="kk-blog-intro" dir="auto">' . nl2br(self::e($t['description'])) . '</p>';
        } elseif (!empty($get['month']) && preg_match('/^\d{4}-\d{2}$/', (string)$get['month'])) {
            $f['month'] = $params['month'] = (string)$get['month'];
            $title = date('F Y', strtotime($f['month'] . '-01'));
        } elseif (trim((string)($get['s'] ?? '')) !== '') {
            $f['search'] = $params['s'] = mb_substr(trim((string)$get['s']), 0, 100);
            $title = sprintf(__('Search: %s'), $f['search']);
        } else {
            $f['sticky_first'] = $page === 1;
            if ($page === 1 && ($t = trim((string)Settings::get('blog_intro'))) !== '') $intro = '<p class="kk-blog-intro">' . self::e($t) . '</p>';
        }
        $r = Posts::query($f);
        $pages = (int)ceil($r['total'] / $per);
        $main = $intro;
        if (!$r['rows']) {
            $main .= '<p class="kk-muted">' . (isset($f['search']) ? __('No posts match your search.') : __('No posts yet.')) . '</p>';
        } else {
            $main .= '<div class="kk-post-list">';
            foreach ($r['rows'] as $i => $p) $main .= self::card($p, $i === 0 && $page === 1 && !$params);
            $main .= '</div>' . self::pager($page, $pages, $params);
        }
        return [$title, '<div class="kk-blog">' . '<div class="kk-blog-main">' . $main . '</div>' . self::sidebar((string)($f['search'] ?? '')) . '</div>', 200];
    }

    public static function previewKey(int $id): string
    {
        $secret = (string)Settings::get('blog_preview_secret');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(16));
            Settings::save(['blog_preview_secret' => $secret]);
        }
        return substr(hash_hmac('sha256', 'preview-' . $id, $secret), 0, 24);
    }

    /** One post or page. Returns [title, html, status code, meta]. */
    public static function single(string $type, string $slug, string $previewKey = ''): array
    {
        $p = Posts::bySlug($type, $slug, $previewKey !== '');
        if ($p && $p['status'] !== 'publish' && !hash_equals(self::previewKey((int)$p['id']), $previewKey)) $p = null;
        if (!$p) return [__('Not found'), '<p class="kk-muted">' . __('This post could not be found.') . '</p><p><a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=blog">' . self::e(self::title()) . '</a></p>', 404, []];

        $notice = '';
        $comments = '';
        if ($type === 'post' && Comments::open($p) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['kk_comment'])) {
            $res = self::submitComment($p);
            $notice = $res;
            $p = Posts::get((int)$p['id']);
        }

        $terms = $type === 'post' ? Terms::forPost((int)$p['id']) : ['category' => [], 'tag' => []];
        $html = '<article class="kk-entry">';
        if ($p['status'] !== 'publish') $html .= '<p class="kk-alert kk-alert--info">' . self::e(sprintf(__('Preview: this %s is not published.'), $type === 'page' ? __('page') : __('post'))) . '</p>';
        if ($type === 'post') {
            $html .= '<p class="kk-entry__meta">';
            foreach ($terms['category'] as $c) $html .= '<a class="kk-entry__cat" href="' . self::e(Terms::url($c)) . '">' . self::e($c['name']) . '</a>';
            $html .= '<time datetime="' . self::e(date('c', strtotime((string)$p['published_at']))) . '">' . self::e(self::date($p['published_at'])) . '</time>'
                . ($p['author_name'] !== '' ? ' · ' . self::e($p['author_name']) : '')
                . ' · ' . self::e(sprintf(__('%d min read'), Text::readingMinutes((string)$p['content']))) . '</p>';
        }
        if ($img = self::featured($p, 1600, 'kk-entry__img')) {
            $m = Media::get((int)$p['featured_media']);
            $html .= '<figure class="kk-entry__figure">' . $img . ($m && $m['caption'] ? '<figcaption>' . self::e($m['caption']) . '</figcaption>' : '') . '</figure>';
        }
        $html .= '<div class="kk-entry__content" dir="auto">' . self::content((string)$p['content']) . '</div>';

        if ($terms['tag']) {
            $html .= '<p class="kk-entry__tags">';
            foreach ($terms['tag'] as $t) $html .= '<a href="' . self::e(Terms::url($t)) . '">#' . self::e($t['name']) . '</a> ';
            $html .= '</p>';
        }
        // Books mentioned, gathered at the end (only when not already shown as cards in the text).
        $ids = self::bookIds((string)$p['content']);
        if (count($ids) > 1) $html .= '<section class="kk-entry__books"><h2>' . __('Books in this post') . '</h2>' . self::bookCards($ids, 'kk-postbooks kk-postbooks--grid') . '</section>';

        if ($type === 'post') {
            $adj = Posts::adjacent($p);
            if ($adj['prev'] || $adj['next']) {
                $html .= '<nav class="kk-entry__adjacent" aria-label="' . __('More posts') . '">'
                    . ($adj['prev'] ? '<a rel="prev" href="' . self::e(Posts::url($adj['prev'])) . '"><span>← ' . __('Previous') . '</span>' . self::e($adj['prev']['title']) . '</a>' : '<span></span>')
                    . ($adj['next'] ? '<a rel="next" href="' . self::e(Posts::url($adj['next'])) . '"><span>' . __('Next') . ' →</span>' . self::e($adj['next']['title']) . '</a>' : '<span></span>')
                    . '</nav>';
            }
            $comments = self::comments($p, $notice);
        }
        $html .= '</article>' . $comments;

        $meta = [
            'description' => $p['meta_description'] !== '' ? $p['meta_description'] : ($p['excerpt'] ?: Text::excerpt((string)$p['content'], 30)),
            'image' => ($m = Media::get((int)$p['featured_media'])) ? Media::url($m, 1200) : '',
        ];
        $wrap = $type === 'post' ? '<div class="kk-blog kk-blog--single"><div class="kk-blog-main">' . $html . '</div>' . self::sidebar() . '</div>' : '<div class="kk-page">' . $html . '</div>';
        return [$p['title'], $wrap, 200, $meta];
    }

    private static function submitComment(array $p): string
    {
        $post = $_POST;
        if (!hash_equals((string)($_SESSION['kk_form_token'] ?? ''), (string)($post['kk_token'] ?? ''))) {
            return '<p class="kk-alert kk-alert--err" role="alert">' . __('Your session expired. Please send your comment again.') . '</p>';
        }
        if (trim((string)($post['website'] ?? '')) !== '' || time() - (int)($_SESSION['kk_form_shown'] ?? 0) < 3) {
            return '<p class="kk-alert kk-alert--ok" role="status">' . __('Thank you. Your comment is waiting for approval.') . '</p>';
        }
        $r = Comments::submit($p, $post);
        if (!$r['ok']) {
            $_SESSION['kk_comment_old'] = $post;
            return '<p class="kk-alert kk-alert--err" role="alert">' . self::e(implode(' ', $r['errors'])) . '</p>';
        }
        unset($_SESSION['kk_comment_old']);
        return '<p class="kk-alert kk-alert--ok" role="status">' . ($r['status'] === 'approved' ? __('Thank you for your comment.') : __('Thank you. Your comment is waiting for approval.')) . '</p>';
    }

    private static function comments(array $p, string $notice): string
    {
        $threads = Comments::threads((int)$p['id']);
        $open = Comments::open($p);
        if (!$threads && !$open) return '';
        $n = (int)$p['comment_count'];
        $out = '<section class="kk-comments" id="comments"><h2>' . self::e($n ? sprintf($n === 1 ? __('%d comment') : __('%d comments'), $n) : __('Comments')) . '</h2>';
        $one = function (array $c, bool $reply) use ($open) {
            return '<li class="kk-comment' . ($c['is_owner'] ? ' kk-comment--owner' : '') . '" id="comment-' . (int)$c['id'] . '">'
                . '<p class="kk-comment__head"><strong>' . ($c['url'] ? '<a href="' . self::e($c['url']) . '" rel="nofollow ugc noopener" target="_blank">' . self::e($c['author']) . '</a>' : self::e($c['author'])) . '</strong>'
                . ($c['is_owner'] ? ' <span class="kk-badge kk-badge--navy">' . __('Librarian') . '</span>' : '')
                . ' <time datetime="' . self::e(date('c', strtotime($c['created_at']))) . '">' . self::e(date('j M Y, H:i', strtotime($c['created_at']))) . '</time></p>'
                . '<div class="kk-comment__body" dir="auto">' . nl2br(self::e($c['content'])) . '</div>'
                . (!$reply && $open ? '<p class="kk-comment__reply"><a href="#kk-comment-form" data-reply="' . (int)$c['id'] . '" data-name="' . self::e($c['author']) . '">' . __('Reply') . '</a></p>' : '');
        };
        if ($threads) {
            $out .= '<ol class="kk-comment-list">';
            foreach ($threads as $t) {
                $out .= $one($t['comment'], false);
                if ($t['replies']) {
                    $out .= '<ol class="kk-comment-list kk-comment-list--replies">';
                    foreach ($t['replies'] as $r) $out .= $one($r, true) . '</li>';
                    $out .= '</ol>';
                }
                $out .= '</li>';
            }
            $out .= '</ol>';
        }
        $out .= $notice;
        if ($open) {
            $old = $_SESSION['kk_comment_old'] ?? [];
            $v = fn($k) => self::e($old[$k] ?? '');
            $token = Messages::token();
            $out .= '<form class="kk-form kk-comment-form" id="kk-comment-form" method="post" action="' . self::e(Posts::url($p)) . '#comments" novalidate>'
                . '<h3>' . __('Leave a comment') . '</h3>'
                . '<p class="kk-muted kk-comment-form__note">' . (Settings::get('blog_moderate') ? __('Comments appear after the librarian approves them. Your email is never shown.') : __('Your email is never shown.')) . '</p>'
                . '<p class="kk-comment-form__replying" hidden>' . __('Replying to') . ' <strong></strong> <button type="button" class="kk-link-btn" data-cancel-reply>' . __('Cancel') . '</button></p>'
                . '<input type="hidden" name="kk_comment" value="1"><input type="hidden" name="parent" value="0"><input type="hidden" name="kk_token" value="' . self::e($token) . '">'
                . '<div class="kk-hp" aria-hidden="true"><label for="kk-c-website">Website</label><input id="kk-c-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
                . '<div class="kk-field"><label for="kk-c-comment">' . __('Comment') . ' <span class="kk-req" aria-hidden="true">*</span></label><textarea class="kk-input" id="kk-c-comment" name="comment" rows="5" required dir="auto">' . $v('comment') . '</textarea></div>'
                . '<div class="kk-form__row">'
                . '<div class="kk-field"><label for="kk-c-author">' . __('Name') . ' <span class="kk-req" aria-hidden="true">*</span></label><input class="kk-input" id="kk-c-author" name="author" autocomplete="name" required value="' . $v('author') . '"></div>'
                . '<div class="kk-field"><label for="kk-c-email">' . __('Email') . ' <span class="kk-req" aria-hidden="true">*</span></label><input class="kk-input" id="kk-c-email" name="email" type="email" autocomplete="email" required value="' . $v('email') . '"></div>'
                . '</div>'
                . '<div class="kk-field"><label for="kk-c-url">' . __('Website (optional)') . '</label><input class="kk-input" id="kk-c-url" name="url" type="url" autocomplete="url" value="' . $v('url') . '"></div>'
                . '<button type="submit" class="kk-btn kk-btn--primary">' . __('Post comment') . '</button></form>';
        }
        return $out . '</section>';
    }

    /** "From the journal" strip for the home page. */
    public static function homeSection(): string
    {
        $n = (int)Settings::get('blog_home_count');
        if (!Posts::enabled() || $n < 1) return '';
        $r = Posts::query(['type' => 'post', 'status' => 'public', 'per_page' => $n, 'sticky_first' => true]);
        if (!$r['rows']) return '';
        $out = '<section class="kk-section kk-home-blog"><div class="container"><div class="kk-section__head"><div>'
            . '<span class="kk-label">' . self::e(self::title()) . '</span><h2 class="kk-section__title">' . __('Latest Writing') . '</h2></div>'
            . '<a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=blog">' . __('All posts') . ' &rarr;</a></div><div class="kk-post-list kk-post-list--home">';
        foreach ($r['rows'] as $p) $out .= self::card($p);
        return $out . '</div></div></section>';
    }

    public static function rss(): void
    {
        global $sysconf;
        while (ob_get_level() > 0) ob_end_clean();
        if (!Posts::enabled() || !Settings::get('enable_rss')) { http_response_code(404); exit('Not found'); }
        $base = \SLiMS\Kutubkhanah\Feed::baseUrl();
        $x = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $r = Posts::query(['type' => 'post', 'status' => 'public', 'per_page' => 20]);
        $name = (string)($sysconf['library_name'] ?? 'Kutubkhanah');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n<channel>\n"
            . '<title>' . $x($name . ' · ' . self::title()) . "</title>\n<link>" . $x($base . 'index.php?p=blog') . "</link>\n"
            . '<atom:link href="' . $x($base . 'index.php?p=blog-rss') . '" rel="self" type="application/rss+xml"/>' . "\n"
            . '<description>' . $x((string)Settings::get('blog_intro')) . "</description>\n";
        foreach ($r['rows'] as $p) {
            $url = $base . ltrim(substr(Posts::url($p), strlen(SWB)), '/');
            $html = self::content((string)$p['content']);
            $html = preg_replace('#(src|href)="(?!https?:|mailto:|tel:|\#)/?#i', '$1="' . $base, $html);
            $out .= "<item>\n<title>" . $x($p['title']) . "</title>\n<link>" . $x($url) . "</link>\n<guid isPermaLink=\"true\">" . $x($url) . "</guid>\n"
                . '<pubDate>' . date(DATE_RSS, strtotime((string)$p['published_at'])) . "</pubDate>\n"
                . ($p['author_name'] ? '<dc:creator>' . $x($p['author_name']) . "</dc:creator>\n" : '');
            foreach (Terms::forPost((int)$p['id'])['category'] as $c) $out .= '<category>' . $x($c['name']) . "</category>\n";
            $out .= '<description>' . $x($p['excerpt'] ?: Text::excerpt((string)$p['content'])) . "</description>\n"
                . '<content:encoded><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $html) . "]]></content:encoded>\n</item>\n";
        }
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $out . "</channel>\n</rss>\n";
        exit;
    }
}
