<?php
/**
 * Kutubkhanah blog: comments with moderation (pending, approved, spam, trash), one level of replies,
 * and the same spam protection as the contact form.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

use SLiMS\Kutubkhanah\Settings;

class Comments
{
    const STATUSES = ['pending', 'approved', 'spam', 'trash'];

    private static function db(): \PDO
    {
        return Posts::db();
    }

    public static function statusLabels(): array
    {
        return ['pending' => __('Pending'), 'approved' => __('Approved'), 'spam' => __('Spam'), 'trash' => __('Trash')];
    }

    public static function open(array $post): bool
    {
        return Settings::get('blog_comments') && !empty($post['comment_status']) && $post['status'] === 'publish' && $post['type'] === 'post';
    }

    /**
     * A visitor's comment. Returns ['ok' => bool, 'errors' => [], 'status' => 'pending'|'approved'].
     */
    public static function submit(array $post, array $in, bool $isOwner = false, string $ownerName = ''): array
    {
        if (!self::open($post)) return ['ok' => false, 'errors' => ['form' => __('Comments are closed for this post.')]];
        $v = fn($k, $n) => mb_substr(trim(strip_tags((string)($in[$k] ?? ''))), 0, $n);
        $author = $isOwner ? $ownerName : $v('author', 100);
        $email = $isOwner ? '' : $v('email', 254);
        $url = $v('url', 300);
        $content = mb_substr(trim(strip_tags((string)($in['comment'] ?? ''))), 0, 5000);
        $errors = [];
        if ($author === '') $errors['author'] = __('Please enter your name.');
        if (!$isOwner && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = __('Please enter a valid email address.');
        if ($url !== '' && !(preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL))) $url = '';
        if (mb_strlen($content) < 2) $errors['comment'] = __('Please write a comment.');
        if ($errors) return ['ok' => false, 'errors' => $errors];

        $ip = mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        // Simple flood control: 3 comments per address per 10 minutes.
        $recent = self::db()->prepare('SELECT COUNT(*) FROM kk_comment WHERE ip = ? AND created_at > ?');
        $recent->execute([$ip, date('Y-m-d H:i:s', time() - 600)]);
        if (!$isOwner && (int)$recent->fetchColumn() >= 3) return ['ok' => false, 'errors' => ['form' => __('Please wait a few minutes before commenting again.')]];

        $parent = (int)($in['parent'] ?? 0);
        if ($parent) {
            $chk = self::db()->prepare("SELECT parent_id FROM kk_comment WHERE id = ? AND post_id = ? AND status = 'approved'");
            $chk->execute([$parent, $post['id']]);
            $pp = $chk->fetchColumn();
            $parent = $pp === false ? 0 : ((int)$pp ?: $parent); // replies stay one level deep
        }
        // Links are a spam signal: more than two always waits for moderation.
        $links = preg_match_all('#https?://#i', $content);
        $status = $isOwner ? 'approved' : ((Settings::get('blog_moderate') || $links > 2) ? 'pending' : 'approved');

        self::db()->prepare('INSERT INTO kk_comment (post_id, parent_id, author, email, url, content, status, is_owner, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$post['id'], $parent, $author, $email, $url, $content, $status, $isOwner ? 1 : 0, $ip, date('Y-m-d H:i:s')]);
        self::recount((int)$post['id']);
        if (!$isOwner) self::notify($post, $author, $content, $status);
        return ['ok' => true, 'errors' => [], 'status' => $status];
    }

    private static function notify(array $post, string $author, string $content, string $status): void
    {
        global $sysconf;
        $to = (string)Settings::get('notify_email');
        if (!Settings::get('blog_notify') || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        try {
            $title = '[' . ($sysconf['library_name'] ?? 'Kutubkhanah') . '] ' . sprintf(__('New comment on "%s"'), $post['title']);
            $body = sprintf(__('%s wrote:'), $author) . "\n\n" . $content . "\n\n"
                . ($status === 'pending' ? __('It is waiting for your approval in Admin > Kutubkhanah > Comments.') : '');
            \SLiMS\Mail::to($to, 'Librarian')->subject($title)->message(nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')))->send();
        } catch (\Throwable $e) {
            error_log('Kutubkhanah comment notification: ' . $e->getMessage());
        }
    }

    public static function recount(int $postId): void
    {
        self::db()->prepare("UPDATE kk_post SET comment_count = (SELECT COUNT(*) FROM kk_comment WHERE post_id = ? AND status = 'approved') WHERE id = ?")
            ->execute([$postId, $postId]);
    }

    /** Approved comments for a post, as threads: [['comment' => .., 'replies' => [..]], ...]. */
    public static function threads(int $postId): array
    {
        $st = self::db()->prepare("SELECT * FROM kk_comment WHERE post_id = ? AND status = 'approved' ORDER BY created_at, id");
        $st->execute([$postId]);
        $top = [];
        $replies = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            if ((int)$c['parent_id']) $replies[(int)$c['parent_id']][] = $c;
            else $top[(int)$c['id']] = $c;
        }
        $out = [];
        foreach ($top as $id => $c) $out[] = ['comment' => $c, 'replies' => $replies[$id] ?? []];
        return $out;
    }

    public static function adminList(string $status, int $page = 1, int $per = 30): array
    {
        $status = in_array($status, self::STATUSES, true) ? $status : 'pending';
        $count = self::db()->prepare('SELECT COUNT(*) FROM kk_comment WHERE status = ?');
        $count->execute([$status]);
        $st = self::db()->prepare('SELECT c.*, p.title AS post_title, p.slug AS post_slug, p.type AS post_type FROM kk_comment c
            LEFT JOIN kk_post p ON p.id = c.post_id WHERE c.status = ? ORDER BY c.created_at DESC LIMIT ' . ((max(1, $page) - 1) * $per) . ', ' . $per);
        $st->execute([$status]);
        return ['rows' => $st->fetchAll(\PDO::FETCH_ASSOC), 'total' => (int)$count->fetchColumn()];
    }

    public static function counts(): array
    {
        return self::db()->query('SELECT status, COUNT(*) FROM kk_comment GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) return false;
        $st = self::db()->prepare('UPDATE kk_comment SET status = ? WHERE id = ?');
        $st->execute([$status, $id]);
        $pid = self::db()->prepare('SELECT post_id FROM kk_comment WHERE id = ?');
        $pid->execute([$id]);
        if ($p = (int)$pid->fetchColumn()) self::recount($p);
        return $st->rowCount() > 0;
    }

    public static function delete(int $id): bool
    {
        $pid = self::db()->prepare('SELECT post_id FROM kk_comment WHERE id = ?');
        $pid->execute([$id]);
        $p = (int)$pid->fetchColumn();
        self::db()->prepare('UPDATE kk_comment SET parent_id = 0 WHERE parent_id = ?')->execute([$id]);
        $st = self::db()->prepare('DELETE FROM kk_comment WHERE id = ?');
        $st->execute([$id]);
        if ($p) self::recount($p);
        return $st->rowCount() > 0;
    }

    /** The librarian's reply from the admin. */
    public static function reply(int $id, string $text, string $name): bool
    {
        $st = self::db()->prepare('SELECT c.*, p.status AS post_status FROM kk_comment c JOIN kk_post p ON p.id = c.post_id WHERE c.id = ?');
        $st->execute([$id]);
        $c = $st->fetch(\PDO::FETCH_ASSOC);
        $text = mb_substr(trim(strip_tags($text)), 0, 5000);
        if (!$c || $text === '') return false;
        if ($c['status'] !== 'approved') self::setStatus($id, 'approved');
        self::db()->prepare("INSERT INTO kk_comment (post_id, parent_id, author, content, status, is_owner, created_at) VALUES (?, ?, ?, ?, 'approved', 1, ?)")
            ->execute([$c['post_id'], (int)$c['parent_id'] ?: $id, mb_substr($name, 0, 100), $text, date('Y-m-d H:i:s')]);
        self::recount((int)$c['post_id']);
        return true;
    }
}
