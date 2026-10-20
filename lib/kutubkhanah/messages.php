<?php
/**
 * Kutubkhanah: public Contact and Request Access forms (from the Private Library design)
 * and their admin Inbox. Submissions are stored in kk_message; an email notice is sent
 * when Settings > Library > "Notify email" is set and the base system mail is configured.
 *
 * Abuse protection: per-session token, a hidden honeypot field, a minimum fill time
 * and a limit of 3 submissions per 10 minutes per session.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Messages
{
    const STATUSES = ['new', 'approved', 'declined', 'closed'];

    public static function token(): string
    {
        if (empty($_SESSION['kk_form_token'])) $_SESSION['kk_form_token'] = bin2hex(random_bytes(16));
        $_SESSION['kk_form_shown'] = time();
        return $_SESSION['kk_form_token'];
    }

    /** @return array{ok:bool,message:string,errors:array} */
    public static function submit(string $type, array $post): array
    {
        $fail = fn(string $m, array $errors = []) => ['ok' => false, 'message' => $m, 'errors' => $errors];

        if (!in_array($type, ['contact', 'access'], true)) return $fail(__('Unknown form.'));
        if (!hash_equals((string)($_SESSION['kk_form_token'] ?? ''), (string)($post['kk_token'] ?? ''))) {
            return $fail(__('Your session expired. Please submit the form again.'));
        }
        // Bots fill every field and submit instantly.
        if (trim((string)($post['website'] ?? '')) !== '' || time() - (int)($_SESSION['kk_form_shown'] ?? 0) < 3) {
            return ['ok' => true, 'message' => self::thanks($type), 'errors' => []];
        }
        $recent = array_filter($_SESSION['kk_form_times'] ?? [], fn($t) => $t > time() - 600);
        if (count($recent) >= 3) return $fail(__('Too many messages in a short time. Please try again later.'));

        $v = fn($k, $n) => mb_substr(trim(strip_tags((string)($post[$k] ?? ''))), 0, $n);
        $name = $v('name', 200);
        $email = trim((string)($post['email'] ?? ''));
        $subject = $type === 'contact' ? $v('subject', 400) : 'Access request';
        $affiliation = $type === 'access' ? $v('affiliation', 300) : '';
        $body = mb_substr(trim(strip_tags((string)($post[$type === 'contact' ? 'message' : 'reason'] ?? ''))), 0, 5000);

        $errors = [];
        if ($name === '') $errors['name'] = __('Please enter your name.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $errors['email'] = __('Please enter a valid email address.');
        if ($type === 'contact' && $subject === '') $errors['subject'] = __('Please enter a subject.');
        if ($body === '') $errors[$type === 'contact' ? 'message' : 'reason'] = $type === 'contact' ? __('Please write a message.') : __('Please tell us why you would like access.');
        if ($errors) return $fail(__('Please check the highlighted fields.'), $errors);

        $ok = DB::getInstance()->prepare(
            'INSERT INTO kk_message (type, name, email, subject, affiliation, body, status, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, \'new\', ?, ?)'
        )->execute([$type, $name, $email, $subject, $affiliation, $body, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), date('Y-m-d H:i:s')]);
        if (!$ok) return $fail(__('Your message could not be saved. Please try again.'));

        $recent[] = time();
        $_SESSION['kk_form_times'] = array_values($recent);
        $_SESSION['kk_form_token'] = bin2hex(random_bytes(16));
        self::notify($type, $name, $email, $subject, $affiliation, $body);
        return ['ok' => true, 'message' => self::thanks($type), 'errors' => []];
    }

    private static function thanks(string $type): string
    {
        return $type === 'access'
            ? __('Thank you. Your access request has been received and will be reviewed personally.')
            : __('Thank you. Your message has been received.');
    }

    private static function notify(string $type, string $name, string $email, string $subject, string $affiliation, string $body): void
    {
        global $sysconf;
        $to = (string)Settings::get('notify_email');
        if ($to === '' || !function_exists('config') || !config('mail')) return;
        try {
            $title = '[' . ($sysconf['library_name'] ?? 'Kutubkhanah') . '] ' . ($type === 'access' ? 'Access request: ' . $name : 'Contact: ' . $subject);
            $text = "Name: $name\nEmail: $email\n" . ($affiliation !== '' ? "Affiliation: $affiliation\n" : '') . "\n$body\n";
            \SLiMS\Mail::to($to, 'Librarian')->subject($title)->message(nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')))->send();
        } catch (\Throwable $e) {
            error_log('Kutubkhanah notify: ' . $e->getMessage());
        }
    }

    /* ---------- Admin ---------- */

    public static function counts(): array
    {
        $out = ['contact' => 0, 'access' => 0, 'new' => 0];
        $q = DB::getInstance()->query("SELECT type, SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS n FROM kk_message GROUP BY type");
        foreach ($q ? $q->fetchAll(\PDO::FETCH_ASSOC) : [] as $r) {
            $out[$r['type']] = (int)$r['n'];
            $out['new'] += (int)$r['n'];
        }
        return $out;
    }

    public static function list(string $type, string $status, int $limit = 100): array
    {
        $sql = 'SELECT * FROM kk_message WHERE type = ?' . ($status !== '' ? ' AND status = ?' : '') . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit;
        $st = DB::getInstance()->prepare($sql);
        $st->execute($status !== '' ? [$type, $status] : [$type]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) return false;
        return DB::getInstance()->prepare('UPDATE kk_message SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public static function delete(int $id): bool
    {
        return DB::getInstance()->prepare('DELETE FROM kk_message WHERE id = ?')->execute([$id]);
    }
}
