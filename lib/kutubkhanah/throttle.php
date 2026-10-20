<?php
/**
 * Kutubkhanah: lock logins after repeated failures (from OpenBiblio 0.8.1).
 *
 * A username is locked for the visitor's address after N failures within the lockout window.
 * An address is also locked on its own after 3 × N failures, which stops password spraying
 * across many usernames without letting a stranger lock you out of your own account.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Throttle
{
    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    public static function enabled(): bool
    {
        return (bool)Settings::get('lockout_enable');
    }

    private static function limit(): int
    {
        return max(3, min(20, (int)Settings::get('lockout_attempts')));
    }

    private static function minutes(): int
    {
        return max(1, min(1440, (int)Settings::get('lockout_minutes')));
    }

    public static function ip(): string
    {
        return function_exists('ip') ? mb_substr((string)ip(), 0, 45) : mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /** Minutes left on a lock, or 0 when the login may go ahead. */
    public static function lockedFor(string $scope, string $username): int
    {
        if (!self::enabled()) return 0;
        $since = date('Y-m-d H:i:s', time() - self::minutes() * 60);
        $ip = self::ip();
        $user = mb_strtolower(mb_substr(trim($username), 0, 100));

        $st = self::db()->prepare('SELECT COUNT(*), MIN(attempted_at) FROM kk_login_attempt WHERE scope = ? AND username = ? AND ip = ? AND attempted_at >= ?');
        $st->execute([$scope, $user, $ip, $since]);
        [$n, $first] = $st->fetch(\PDO::FETCH_NUM);
        $locked = (int)$n >= self::limit();

        if (!$locked) {
            $st = self::db()->prepare('SELECT COUNT(*), MIN(attempted_at) FROM kk_login_attempt WHERE scope = ? AND ip = ? AND attempted_at >= ?');
            $st->execute([$scope, $ip, $since]);
            [$n, $first] = $st->fetch(\PDO::FETCH_NUM);
            $locked = (int)$n >= self::limit() * 3;
        }
        if (!$locked || !$first) return 0;
        $left = (strtotime($first) + self::minutes() * 60) - time();
        return max(1, (int)ceil($left / 60));
    }

    public static function fail(string $scope, string $username): void
    {
        if (!self::enabled()) return;
        self::db()->prepare('INSERT INTO kk_login_attempt (scope, username, ip, attempted_at) VALUES (?, ?, ?, ?)')
            ->execute([$scope, mb_strtolower(mb_substr(trim($username), 0, 100)), self::ip(), date('Y-m-d H:i:s')]);
        // Keep the table small.
        self::db()->prepare('DELETE FROM kk_login_attempt WHERE attempted_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400 * 2)]);
    }

    public static function clear(string $scope, string $username): void
    {
        self::db()->prepare('DELETE FROM kk_login_attempt WHERE scope = ? AND username = ? AND ip = ?')
            ->execute([$scope, mb_strtolower(mb_substr(trim($username), 0, 100)), self::ip()]);
    }

    public static function message(int $minutes): string
    {
        return $minutes === 1
            ? __('Too many failed sign-in attempts. Please try again in 1 minute.')
            : sprintf(__('Too many failed sign-in attempts. Please try again in %d minutes.'), $minutes);
    }
}
