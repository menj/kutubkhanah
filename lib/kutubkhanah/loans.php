<?php
/**
 * Kutubkhanah: a home lending log (after OpenBiblio's simple circulation).
 * "Lent to Ahmad on 3 March, due 2 April" — no member accounts, fines or loan rules.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Loans
{
    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    private static function date(?string $d): ?string
    {
        $d = trim((string)$d);
        if ($d === '') return null;
        $t = \DateTime::createFromFormat('!Y-m-d', $d);
        return $t && $t->format('Y-m-d') === $d ? $d : null;
    }

    /** @throws \InvalidArgumentException */
    public static function lend(int $biblioId, array $in): int
    {
        if (Books::title($biblioId) === '') throw new \InvalidArgumentException(__('Book not found.'));
        $borrower = mb_substr(trim(strip_tags((string)($in['borrower'] ?? ''))), 0, 150);
        if ($borrower === '') throw new \InvalidArgumentException(__('Who is borrowing the book?'));
        $lent = self::date($in['lent_on'] ?? '') ?? date('Y-m-d');
        $due = self::date($in['due_on'] ?? '');
        if ($due === null && ($days = (int)Settings::get('loan_days')) > 0) $due = date('Y-m-d', strtotime($lent . ' +' . $days . ' days'));
        if ($due !== null && $due < $lent) throw new \InvalidArgumentException(__('The due date is before the lending date.'));

        $db = self::db();
        $db->prepare('INSERT INTO kk_loan (biblio_id, borrower, contact, lent_on, due_on, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$biblioId, $borrower, mb_substr(trim(strip_tags((string)($in['contact'] ?? ''))), 0, 150), $lent, $due,
                mb_substr(trim(strip_tags((string)($in['notes'] ?? ''))), 0, 2000), date('Y-m-d H:i:s')]);
        return (int)$db->lastInsertId();
    }

    public static function giveBack(int $id, ?string $on = null): bool
    {
        $st = self::db()->prepare('UPDATE kk_loan SET returned_on = ? WHERE id = ? AND returned_on IS NULL');
        $st->execute([self::date($on) ?? date('Y-m-d'), $id]);
        return $st->rowCount() > 0;
    }

    public static function delete(int $id): bool
    {
        $st = self::db()->prepare('DELETE FROM kk_loan WHERE id = ?');
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** Loans not yet returned, overdue first. */
    public static function open(): array
    {
        return self::db()->query(
            "SELECT l.*, b.title, b.image, b.sor FROM kk_loan l JOIN biblio b ON b.biblio_id = l.biblio_id
             WHERE l.returned_on IS NULL ORDER BY (l.due_on IS NULL), l.due_on, l.lent_on"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function recentReturns(int $limit = 20): array
    {
        $st = self::db()->prepare(
            "SELECT l.*, b.title, b.image, b.sor FROM kk_loan l JOIN biblio b ON b.biblio_id = l.biblio_id
             WHERE l.returned_on IS NOT NULL ORDER BY l.returned_on DESC, l.id DESC LIMIT " . max(1, min(200, $limit))
        );
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function forBook(int $biblioId): array
    {
        $st = self::db()->prepare('SELECT * FROM kk_loan WHERE biblio_id = ? ORDER BY (returned_on IS NULL) DESC, lent_on DESC, id DESC');
        $st->execute([$biblioId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** The open loan for a book, if it is out. */
    public static function current(int $biblioId): ?array
    {
        $st = self::db()->prepare('SELECT * FROM kk_loan WHERE biblio_id = ? AND returned_on IS NULL ORDER BY lent_on DESC LIMIT 1');
        $st->execute([$biblioId]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function countOpen(): int
    {
        return (int)self::db()->query('SELECT COUNT(*) FROM kk_loan WHERE returned_on IS NULL')->fetchColumn();
    }

    public static function isOverdue(array $loan): bool
    {
        return empty($loan['returned_on']) && !empty($loan['due_on']) && $loan['due_on'] < date('Y-m-d');
    }

    /** Distinct past borrowers, for the name suggestions. */
    public static function borrowers(): array
    {
        return self::db()->query('SELECT borrower, MAX(contact) AS contact FROM kk_loan GROUP BY borrower ORDER BY MAX(lent_on) DESC LIMIT 200')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }
}
