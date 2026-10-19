<?php
/**
 * Kutubkhanah: duplicate finder and merge (after PMB), and editions grouped as one work.
 * Duplicates are the same book entered twice; editions are different books of the same work
 * (another publisher, year or translation) that you want shown together.
 *
 * Everything here uses portable SQL, so it runs on MySQL, SQLite and PostgreSQL alike.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Duplicates
{
    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    /** Lower-case letters and digits only, so "The Hobbit!" and "the  hobbit" match. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim(strip_tags($s)));
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $s) ?? '';
    }

    private static function catalogue(): array
    {
        $rows = self::db()->query(
            "SELECT b.biblio_id AS id, b.title, b.isbn_issn AS isbn, b.publish_year AS year, b.publisher_id, b.image, b.edition,
                    (SELECT a.author_name FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id
                      WHERE ba.biblio_id = b.biblio_id ORDER BY ba.level, a.author_name LIMIT 1) AS author,
                    (SELECT COUNT(*) FROM item i WHERE i.biblio_id = b.biblio_id) AS copies,
                    (SELECT COUNT(*) FROM kk_loan l WHERE l.biblio_id = b.biblio_id) AS lent
             FROM biblio b ORDER BY b.biblio_id"
        )->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['isbn13'] = Isbn::normalize((string)$r['isbn']);
            $r['tkey'] = self::norm((string)$r['title']);
            $r['akey'] = self::norm((string)$r['author']);
        }
        return $rows;
    }

    /** Groups of probable duplicates: same ISBN, or same title, author and year. Each group is a list of books. */
    public static function groups(): array
    {
        $rows = self::catalogue();
        $parent = [];
        $find = function (int $x) use (&$parent, &$find) {
            while (($parent[$x] ?? $x) !== $x) { $parent[$x] = $parent[$parent[$x]] ?? $parent[$x]; $x = $parent[$x]; }
            return $x;
        };
        foreach ($rows as $r) $parent[$r['id']] = $r['id'];
        $seen = [];
        foreach ($rows as $r) {
            $keys = [];
            if ($r['isbn13'] !== '') $keys[] = 'i:' . $r['isbn13'];
            if ($r['tkey'] !== '') $keys[] = 't:' . $r['tkey'] . '|' . $r['akey'] . '|' . trim((string)$r['year']);
            foreach ($keys as $k) {
                if (isset($seen[$k])) { $a = $find($seen[$k]); $b = $find($r['id']); if ($a !== $b) $parent[$b] = $a; }
                else $seen[$k] = $r['id'];
            }
        }
        $by = [];
        foreach ($rows as $r) $by[$find($r['id'])][] = $r;
        return array_values(array_filter($by, fn($g) => count($g) > 1));
    }

    /** Probable editions of one work: same title and author, not yet linked together and not duplicates. */
    public static function editionGroups(): array
    {
        $rows = self::catalogue();
        $links = self::db()->query('SELECT biblio_id, work_id FROM kk_work_link')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $by = [];
        foreach ($rows as $r) if ($r['tkey'] !== '') $by[$r['tkey'] . '|' . $r['akey']][] = $r;
        $out = [];
        foreach ($by as $g) {
            if (count($g) < 2) continue;
            $works = array_unique(array_map(fn($r) => $links[$r['id']] ?? ('b' . $r['id']), $g));
            if (count($works) < 2) continue;      // already one work
            $out[] = $g;
        }
        return $out;
    }

    /* ---------- Merge ---------- */

    /** @throws \InvalidArgumentException */
    public static function merge(int $keep, array $drop): int
    {
        $drop = array_values(array_unique(array_filter(array_map('intval', $drop), fn($d) => $d > 0 && $d !== $keep)));
        if ($keep <= 0 || !$drop) throw new \InvalidArgumentException(__('Choose the book to keep and at least one to merge into it.'));
        $db = self::db();
        $exists = $db->prepare('SELECT COUNT(*) FROM biblio WHERE biblio_id = ?');
        foreach (array_merge([$keep], $drop) as $id) {
            $exists->execute([$id]);
            if (!(int)$exists->fetchColumn()) throw new \InvalidArgumentException(__('One of those books no longer exists.'));
        }
        $db->beginTransaction();
        try {
            foreach ($drop as $d) self::mergeOne($db, $keep, $d);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        try { Books::reindex($keep); } catch (\Throwable $e) { error_log('Kutubkhanah reindex: ' . $e->getMessage()); }
        return count($drop);
    }

    private static function column(\PDO $db, string $sql, array $args): array
    {
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }

    private static function mergeOne(\PDO $db, int $keep, int $d): void
    {
        // Plain re-pointing.
        foreach (['item', 'reserve', 'loan_history', 'kk_loan', 'comment', 'biblio_mark'] as $t) {
            $db->prepare("UPDATE $t SET biblio_id = ? WHERE biblio_id = ?")->execute([$keep, $d]);
        }
        $db->prepare('UPDATE kk_wish SET acquired_biblio_id = ? WHERE acquired_biblio_id = ?')->execute([$keep, $d]);
        $db->prepare('UPDATE kk_doc_index SET biblio_id = ? WHERE biblio_id = ?')->execute([$keep, $d]);
        $db->prepare('UPDATE kk_doc_text SET biblio_id = ? WHERE biblio_id = ?')->execute([$keep, $d]);

        // Authors and subjects: add the ones the kept book lacks.
        foreach ([['biblio_author', 'author_id'], ['biblio_topic', 'topic_id']] as [$t, $c]) {
            $have = self::column($db, "SELECT $c FROM $t WHERE biblio_id = ?", [$keep]);
            $st = $db->prepare("SELECT $c, level FROM $t WHERE biblio_id = ?");
            $st->execute([$d]);
            foreach ($st->fetchAll(\PDO::FETCH_NUM) as [$id, $level]) {
                if (!in_array($id, $have)) {
                    $db->prepare("INSERT INTO $t (biblio_id, $c, level) VALUES (?, ?, ?)")->execute([$keep, $id, $level]);
                    $have[] = $id;
                }
            }
            $db->prepare("DELETE FROM $t WHERE biblio_id = ?")->execute([$d]);
        }

        // Digital copies and reading lists: skip what the kept book already has.
        $haveFiles = self::column($db, 'SELECT file_id FROM biblio_attachment WHERE biblio_id = ?', [$keep]);
        $st = $db->prepare('SELECT file_id FROM biblio_attachment WHERE biblio_id = ?');
        $st->execute([$d]);
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $f) {
            if (in_array($f, $haveFiles)) $db->prepare('DELETE FROM biblio_attachment WHERE biblio_id = ? AND file_id = ?')->execute([$d, $f]);
        }
        $db->prepare('UPDATE biblio_attachment SET biblio_id = ? WHERE biblio_id = ?')->execute([$keep, $d]);

        $haveLists = self::column($db, 'SELECT list_id FROM kk_list_item WHERE biblio_id = ?', [$keep]);
        $st = $db->prepare('SELECT list_id FROM kk_list_item WHERE biblio_id = ?');
        $st->execute([$d]);
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $l) {
            if (in_array($l, $haveLists)) $db->prepare('DELETE FROM kk_list_item WHERE biblio_id = ? AND list_id = ?')->execute([$d, $l]);
        }
        $db->prepare('UPDATE kk_list_item SET biblio_id = ? WHERE biblio_id = ?')->execute([$keep, $d]);

        // Custom fields (Kutubkhanah's own): keep the kept book's values, add missing ones.
        $haveF = self::column($db, 'SELECT field_id FROM kk_field_value WHERE biblio_id = ?', [$keep]);
        $st = $db->prepare('SELECT field_id FROM kk_field_value WHERE biblio_id = ?');
        $st->execute([$d]);
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $f) {
            if (in_array($f, $haveF)) $db->prepare('DELETE FROM kk_field_value WHERE biblio_id = ? AND field_id = ?')->execute([$d, $f]);
        }
        $db->prepare('UPDATE kk_field_value SET biblio_id = ? WHERE biblio_id = ?')->execute([$keep, $d]);

        // Editions: the kept book joins the other book's work if it has none.
        $w = self::column($db, 'SELECT work_id FROM kk_work_link WHERE biblio_id = ?', [$d]);
        if ($w && !self::column($db, 'SELECT 1 FROM kk_work_link WHERE biblio_id = ?', [$keep])) {
            $db->prepare('INSERT INTO kk_work_link (biblio_id, work_id) VALUES (?, ?)')->execute([$keep, $w[0]]);
        }
        $db->prepare('DELETE FROM kk_work_link WHERE biblio_id = ?')->execute([$d]);

        // Fill blanks in the catalogue record and in Kutubkhanah's book details.
        self::fillBlanks($db, 'biblio', 'biblio_id', $keep, $d, ['image', 'isbn_issn', 'notes', 'publisher_id', 'publish_place_id', 'publish_year',
            'call_number', 'classification', 'edition', 'series_title', 'collation', 'language_id', 'spec_detail_info']);
        $meta = array_keys(Books::meta($d));
        if ($meta) self::fillBlanks($db, 'kk_book', 'biblio_id', $keep, $d, $meta);

        // Remove the merged record.
        foreach (['kk_book', 'search_biblio', 'biblio_custom', 'biblio'] as $t) {
            $db->prepare("DELETE FROM $t WHERE biblio_id = ?")->execute([$d]);
        }
        $db->prepare('DELETE FROM biblio_relation WHERE biblio_id = ? OR rel_biblio_id = ?')->execute([$d, $d]);
    }

    private static function fillBlanks(\PDO $db, string $table, string $key, int $keep, int $d, array $cols): void
    {
        $get = function (int $id) use ($db, $table, $key) {
            $st = $db->prepare("SELECT * FROM $table WHERE $key = ?");
            $st->execute([$id]);
            return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        };
        $a = $get($keep); $b = $get($d);
        if (!$a || !$b) return;
        $set = []; $args = [];
        foreach ($cols as $c) {
            if (!array_key_exists($c, $a) || $c === $key) continue;
            $blank = fn($v) => $v === null || $v === '' || $v === '0000-00-00';
            if ($blank($a[$c]) && !$blank($b[$c] ?? null)) { $set[] = "$c = ?"; $args[] = $b[$c]; }
        }
        if ($set) { $args[] = $keep; $db->prepare("UPDATE $table SET " . implode(', ', $set) . " WHERE $key = ?")->execute($args); }
    }

    /* ---------- Editions grouped as one work ---------- */

    /** Link books as editions of the same work. */
    public static function linkEditions(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (count($ids) < 2) throw new \InvalidArgumentException(__('Choose at least two books.'));
        $db = self::db();
        $in = implode(',', $ids);
        $existing = $db->query("SELECT work_id FROM kk_work_link WHERE biblio_id IN ($in)")->fetchAll(\PDO::FETCH_COLUMN);
        $work = $existing ? (int)min($existing) : min($ids);
        // Pull in every book already in any of the touched works.
        if ($existing) $db->exec('UPDATE kk_work_link SET work_id = ' . $work . ' WHERE work_id IN (' . implode(',', array_map('intval', $existing)) . ')');
        $have = $db->query("SELECT biblio_id FROM kk_work_link WHERE biblio_id IN ($in)")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            if (!in_array($id, $have)) $db->prepare('INSERT INTO kk_work_link (biblio_id, work_id) VALUES (?, ?)')->execute([$id, $work]);
        }
    }

    public static function unlink(int $id): void
    {
        $db = self::db();
        $db->prepare('DELETE FROM kk_work_link WHERE biblio_id = ?')->execute([$id]);
        // A work left with a single edition is no work at all.
        $db->exec('DELETE FROM kk_work_link WHERE work_id IN (SELECT work_id FROM (SELECT work_id FROM kk_work_link GROUP BY work_id HAVING COUNT(*) < 2) x)');
    }

    /** Other editions of the same work, visible in the OPAC. */
    public static function editionsOf(int $id): array
    {
        $st = self::db()->prepare(
            'SELECT b.biblio_id, b.title, b.publish_year, b.edition, b.image, p.publisher_name
             FROM kk_work_link me JOIN kk_work_link o ON o.work_id = me.work_id AND o.biblio_id <> me.biblio_id
             JOIN biblio b ON b.biblio_id = o.biblio_id LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id
             WHERE me.biblio_id = ? AND b.opac_hide = 0 ORDER BY b.publish_year, b.biblio_id'
        );
        $st->execute([$id]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** All linked works with their editions, for the admin list. */
    public static function works(): array
    {
        $rows = self::db()->query(
            'SELECT w.work_id, b.biblio_id, b.title, b.publish_year, b.edition
             FROM kk_work_link w JOIN biblio b ON b.biblio_id = w.biblio_id ORDER BY w.work_id, b.publish_year, b.biblio_id'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[$r['work_id']][] = $r;
        return $out;
    }
}
