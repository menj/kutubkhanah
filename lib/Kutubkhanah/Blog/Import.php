<?php
/**
 * Kutubkhanah blog: import a WordPress export (Tools > Export > All content, a WXR .xml file):
 * categories, tags, posts, pages, featured images, images in posts and comments.
 * Classic-editor posts get WordPress's own paragraph formatting (wpautop); block-editor posts
 * are kept as they are. Running it twice does not duplicate posts.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

use SLiMS\Kutubkhanah\Http;

class Import
{
    /** @return array{posts:int, pages:int, skipped:int, comments:int, images:int, terms:int, errors:array} */
    public static function run(string $file, array $user, bool $images = true): array
    {
        $xml = @file_get_contents($file);
        if ($xml === false || stripos($xml, '<rss') === false) throw new \InvalidArgumentException(__('This is not a WordPress export file.'));
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$doc || !isset($doc->channel)) throw new \InvalidArgumentException(__('The export file could not be read.'));

        $ns = $doc->getDocNamespaces(true);
        $wpNs = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';
        $contentNs = $ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
        $excerptNs = $ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/';
        $dcNs = $ns['dc'] ?? 'http://purl.org/dc/elements/1.1/';
        $ch = $doc->channel;
        $wpc = $ch->children($wpNs);
        $out = ['posts' => 0, 'pages' => 0, 'skipped' => 0, 'comments' => 0, 'images' => 0, 'terms' => 0, 'errors' => []];
        $db = Posts::db();

        // 1. Categories (parents first) and tags.
        $catBySlug = [];
        $pending = [];
        foreach ($wpc->category as $c) {
            $cc = $c->children($wpNs);
            $pending[] = ['slug' => (string)$cc->category_nicename, 'name' => html_entity_decode((string)$cc->cat_name, ENT_QUOTES, 'UTF-8'),
                          'parent' => (string)$cc->category_parent, 'desc' => (string)$cc->category_description];
        }
        for ($pass = 0; $pending && $pass < 10; $pass++) {
            foreach ($pending as $k => $c) {
                if ($c['parent'] !== '' && !isset($catBySlug[$c['parent']])) continue;
                $existing = Terms::bySlug('category', Text::slug($c['slug'] ?: $c['name'], 140));
                $catBySlug[$c['slug']] = $existing ? (int)$existing['id']
                    : Terms::save(0, 'category', ['name' => $c['name'], 'slug' => $c['slug'], 'description' => $c['desc'], 'parent' => $catBySlug[$c['parent']] ?? 0]);
                if (!$existing) $out['terms']++;
                unset($pending[$k]);
            }
        }
        foreach ($wpc->tag as $t) {
            $tc = $t->children($wpNs);
            $name = html_entity_decode((string)$tc->tag_name, ENT_QUOTES, 'UTF-8');
            if ($name !== '' && !Terms::bySlug('tag', Text::slug((string)$tc->tag_slug ?: $name, 140))) {
                Terms::save(0, 'tag', ['name' => $name, 'slug' => (string)$tc->tag_slug]);
                $out['terms']++;
            }
        }

        // 2. Attachments: WordPress id → file URL.
        $attachments = [];
        foreach ($ch->item as $item) {
            $w = $item->children($wpNs);
            if ((string)$w->post_type === 'attachment' && (string)$w->attachment_url !== '') {
                $attachments[(int)$w->post_id] = ['url' => (string)$w->attachment_url, 'title' => (string)$item->title,
                    'caption' => (string)$item->children($excerptNs)->encoded];
            }
        }
        $mediaByUrl = [];
        $fetch = function (string $url, string $caption = '') use (&$mediaByUrl, $user, &$out): ?array {
            $key = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', strtok($url, '?'));
            if (isset($mediaByUrl[$key])) return $mediaByUrl[$key];
            [$code, $body] = Http::get($key, 30);
            if ($code !== 200 || $body === '') { [$code, $body] = Http::get($url, 30); }
            if ($code !== 200 || $body === '') return $mediaByUrl[$key] = null;
            try {
                $m = Media::store($body, basename(parse_url($key, PHP_URL_PATH) ?: 'image'), (int)$user['id'], '', $caption);
                $out['images']++;
                return $mediaByUrl[$key] = $m;
            } catch (\Throwable $e) {
                return $mediaByUrl[$key] = null;
            }
        };

        // 3. Posts and pages.
        $postByWp = [];
        $commentQueue = [];
        foreach ($ch->item as $item) {
            $w = $item->children($wpNs);
            $type = (string)$w->post_type;
            if (!in_array($type, ['post', 'page'], true)) continue;
            $status = (string)$w->status;
            if (!in_array($status, ['publish', 'draft', 'private', 'future', 'pending'], true)) { $out['skipped']++; continue; }
            $wpId = (int)$w->post_id;
            $dup = $db->prepare('SELECT id FROM kk_post WHERE wp_id = ? AND type = ?');
            $dup->execute([$wpId, $type]);
            if ($already = (int)$dup->fetchColumn()) { $postByWp[$wpId] = $already; $out['skipped']++; continue; }

            $content = (string)$item->children($contentNs)->encoded;
            if (!str_contains($content, '<!-- wp:')) $content = Wp::autop($content);
            $content = preg_replace('/<!--\s*\/?wp:[^>]*-->\s*/', '', $content);

            $featured = 0;
            if ($images) {
                foreach ($w->postmeta as $pm) {
                    if ((string)$pm->meta_key === '_thumbnail_id' && isset($attachments[(int)$pm->meta_value])) {
                        $a = $attachments[(int)$pm->meta_value];
                        if ($m = $fetch($a['url'], $a['caption'])) $featured = $m['id'];
                    }
                }
                // Images in the text that live on the old site are copied into the media library.
                $site = parse_url((string)$ch->link, PHP_URL_HOST);
                $content = preg_replace_callback('#(<img[^>]+src=")([^"]+)(")#i', function ($m) use ($fetch, $site) {
                    $host = parse_url($m[2], PHP_URL_HOST);
                    if (!$host || ($site && $host !== $site && !str_ends_with($host, 'wp.com'))) return $m[0];
                    $media = $fetch(html_entity_decode($m[2]));
                    return $media ? $m[1] . $media['medium'] . $m[3] : $m[0];
                }, $content);
            }

            $cats = [];
            $tags = [];
            foreach ($item->category as $c) {
                $domain = (string)$c['domain'];
                if ($domain === 'category' && isset($catBySlug[(string)$c['nicename']])) $cats[] = $catBySlug[(string)$c['nicename']];
                if ($domain === 'post_tag') $tags[] = html_entity_decode((string)$c, ENT_QUOTES, 'UTF-8');
            }
            $date = (string)$w->post_date;
            $title = html_entity_decode((string)$item->title, ENT_QUOTES, 'UTF-8');
            try {
                $id = Posts::save(0, [
                    'title' => $title !== '' ? $title : __('(no title)'),
                    'content' => $content,
                    'excerpt' => Text::plain((string)$item->children($excerptNs)->encoded),
                    'slug' => (string)$w->post_name !== '' ? urldecode((string)$w->post_name) : $title,
                    'status' => $status === 'pending' ? 'draft' : $status,
                    'published_at' => $date && $date !== '0000-00-00 00:00:00' ? date('Y-m-d\TH:i', strtotime($date)) : '',
                    'categories' => $cats, 'tags' => implode(',', $tags), 'featured_media' => $featured,
                    'comment_status' => (string)$w->comment_status === 'open' ? 1 : 0,
                    'sticky' => (int)$w->is_sticky, 'menu_order' => (int)$w->menu_order,
                ], ['id' => (int)$user['id'], 'name' => trim((string)$item->children($dcNs)->creator) ?: (string)$user['name']], $type);
                $db->prepare('UPDATE kk_post SET wp_id = ?, created_at = COALESCE(published_at, created_at) WHERE id = ?')->execute([$wpId, $id]);
                $postByWp[$wpId] = $id;
                $out[$type === 'page' ? 'pages' : 'posts']++;
                foreach ($w->comment as $c) $commentQueue[] = [$id, $c];
            } catch (\Throwable $e) {
                $out['errors'][] = $title . ': ' . $e->getMessage();
            }
        }

        // 4. Comments (approved, pending and spam), keeping replies under their parents.
        $commentByWp = [];
        $ins = $db->prepare('INSERT INTO kk_comment (post_id, parent_id, author, email, url, content, status, is_owner, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        usort($commentQueue, fn($a, $b) => (int)$a[1]->comment_id <=> (int)$b[1]->comment_id);
        foreach ($commentQueue as [$postId, $c]) {
            $approved = (string)$c->comment_approved;
            if ($approved === 'trash' || (string)$c->comment_type === 'pingback' || (string)$c->comment_type === 'trackback') continue;
            $status = $approved === '1' ? 'approved' : ($approved === 'spam' ? 'spam' : 'pending');
            $parent = $commentByWp[(int)$c->comment_parent] ?? 0;
            $ins->execute([$postId, $parent, mb_substr(trim((string)$c->comment_author), 0, 100), mb_substr((string)$c->comment_author_email, 0, 254),
                mb_substr((string)$c->comment_author_url, 0, 300), mb_substr(trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", (string)$c->comment_content)), ENT_QUOTES, 'UTF-8')), 0, 5000), $status,
                (int)$c->comment_user_id > 0 ? 1 : 0, mb_substr((string)$c->comment_author_IP, 0, 45), (string)$c->comment_date ?: date('Y-m-d H:i:s')]);
            $commentByWp[(int)$c->comment_id] = (int)$db->lastInsertId();
            $out['comments']++;
        }
        foreach (array_unique(array_values($postByWp)) as $pid) Comments::recount((int)$pid);
        return $out;
    }
}
