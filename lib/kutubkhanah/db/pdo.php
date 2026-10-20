<?php
/**
 * Kutubkhanah: PDO connection for SQLite and PostgreSQL that accepts the MySQL dialect used by the
 * inherited code. Every statement is translated by Dialect first. MySQL itself uses plain PDO.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

class Pdo extends \PDO
{
    public Dialect $dialect;
    private array $uniq = [];
    /** translated SQL => true for statements that used SQL_CALC_FOUND_ROWS */
    private array $calcQueries = [];
    /** [sql, params] of the last SQL_CALC_FOUND_ROWS query */
    private ?array $lastCalc = null;

    /**
     * @param array $node config node: driver, host, port, database, username, password
     * @param bool  $strings return every value as a string, like mysqli does
     */
    public function __construct(array $node, bool $strings = false)
    {
        $driver = Dialect::normaliseDriver((string)($node['driver'] ?? 'mysql'));
        if ($driver === Dialect::SQLITE) {
            $path = (string)($node['database'] ?? '');
            if ($path !== '' && $path[0] !== '/' && !preg_match('#^[A-Za-z]:[\\\\/]#', $path) && $path !== ':memory:') $path = (defined('SB') ? SB : dirname(__DIR__, 3) . '/') . $path;
            parent::__construct('sqlite:' . $path);
        } else {
            $host = (string)($node['host'] ?? 'localhost');
            $dsn = 'pgsql:host=' . $host . ';port=' . (int)($node['port'] ?: 5432) . ';dbname=' . ($node['database'] ?? '');
            parent::__construct($dsn, (string)($node['username'] ?? ''), (string)($node['password'] ?? ''));
        }
        $this->dialect = new Dialect($driver, [$this, 'uniqueKeys']);
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$this]]);
        if ($strings) $this->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);

        if ($driver === Dialect::SQLITE) {
            parent::exec('PRAGMA journal_mode = WAL');
            parent::exec('PRAGMA busy_timeout = 8000');
            parent::exec('PRAGMA synchronous = NORMAL');
            parent::exec('PRAGMA foreign_keys = OFF');
            $this->registerFunctions();
        } else {
            parent::exec("SET client_encoding TO 'UTF8'");
        }
    }

    public function driverName(): string { return $this->dialect->driver; }

    private function registerFunctions(): void
    {
        $f = fn(string $name, callable $fn, int $args = -1) => $this->sqliteCreateFunction($name, $fn, $args, \PDO::SQLITE_DETERMINISTIC);
        $f('regexp', fn($pattern, $value) => $value === null ? null : (int)(@preg_match('~' . str_replace('~', '\~', (string)$pattern) . '~iu', (string)$value) === 1), 2);
        $f('md5', fn($s) => md5((string)$s), 1);
        $f('sha1', fn($s) => sha1((string)$s), 1);
        $f('lower', fn($s) => $s === null ? null : mb_strtolower((string)$s), 1);
        $f('upper', fn($s) => $s === null ? null : mb_strtoupper((string)$s), 1);
    }

    // ---- unique keys, for ON DUPLICATE KEY UPDATE -------------------------------------------------

    /** @return array<int,array<int,string>> */
    public function uniqueKeys(string $table): array
    {
        if (isset($this->uniq[$table])) return $this->uniq[$table];
        $sets = [];
        try {
            if ($this->dialect->isSqlite()) {
                $info = parent::query('PRAGMA table_info("' . str_replace('"', '', $table) . '")')->fetchAll();
                $pk = [];
                foreach ($info as $c) if ((int)$c['pk'] > 0) $pk[(int)$c['pk']] = strtolower($c['name']);
                ksort($pk);
                $uniques = [];
                foreach (parent::query('PRAGMA index_list("' . str_replace('"', '', $table) . '")')->fetchAll() as $ix) {
                    if (!(int)$ix['unique']) continue;
                    $cols = [];
                    foreach (parent::query('PRAGMA index_info("' . str_replace('"', '', $ix['name']) . '")')->fetchAll() as $c) $cols[] = strtolower($c['name']);
                    $uniques[] = $cols;
                }
                $sets = array_merge($uniques, $pk ? [array_values($pk)] : []);
            } else {
                $st = parent::prepare("SELECT i.indisprimary AS pk, array_agg(a.attname ORDER BY k.ord) AS cols
                    FROM pg_index i
                    JOIN pg_class c ON c.oid = i.indrelid
                    JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, ord) ON true
                    JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = k.attnum
                    WHERE c.relname = ? AND c.relnamespace = current_schema()::regnamespace AND i.indisunique
                    GROUP BY i.indexrelid, i.indisprimary ORDER BY i.indisprimary ASC");
                $st->execute([$table]);
                foreach ($st->fetchAll() as $r) $sets[] = array_map('strtolower', explode(',', trim($r['cols'], '{}')));
            }
        } catch (\Throwable) {
        }
        return $this->uniq[$table] = $sets;
    }

    public function forgetSchema(): void { $this->uniq = []; }

    // ---- query pipeline ---------------------------------------------------------------------------

    private function empty(): \PDOStatement
    {
        return parent::query('SELECT 1 WHERE 1 = 0');
    }

    /** Statements that only mean something to MySQL. @return \PDOStatement|null */
    private function intercept(string $sql): ?\PDOStatement
    {
        $s = ltrim($sql);
        if (!preg_match('/^(SET|USE|SHOW|DESCRIBE|DESC|OPTIMIZE|ANALYZE|REPAIR|FLUSH|LOCK|UNLOCK|CHECK|SELECT\s+(?:@@|FOUND_ROWS\s*\(|VERSION\s*\(\s*\)))(?:\b|\s|$|\(|\))/i', $s, $m)) return null;
        $kw = strtoupper(preg_replace('/\s+.*$/s', '', $m[1]));
        $sqlite = $this->dialect->isSqlite();

        if ($kw === 'SELECT') {
            if (stripos($s, 'FOUND_ROWS') !== false) {
                if (!$this->lastCalc) return parent::query('SELECT 0 AS "FOUND_ROWS()"');
                [$q, $params] = $this->lastCalc;
                $q = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?\s*$/i', '', $q);
                $q = preg_replace('/\s+LIMIT\s+\?(\s+OFFSET\s+\?)?\s*$/i', '', $q, -1, $stripped);
                if ($stripped && $params) $params = array_slice($params, 0, count($params) - ($stripped && preg_match('/OFFSET/i', $this->lastCalc[0]) ? 2 : 1));
                $st = parent::prepare('SELECT COUNT(*) AS "FOUND_ROWS()" FROM (' . $q . ') AS kk_found');
                $st->execute($params);
                return $st;
            }
            if (preg_match('/VERSION/i', $s)) return parent::query($sqlite ? 'SELECT sqlite_version() AS "VERSION()"' : 'SELECT version() AS "VERSION()"');
            return parent::query("SELECT '' AS \"value\"");
        }
        if ($kw === 'SHOW') {
            if (preg_match('/^SHOW\s+(?:FULL\s+)?TABLES(?:\s+FROM\s+\S+)?(?:\s+LIKE\s+\'((?:[^\'\\\\]|\\\\.)*)\')?/i', $s, $m)) {
                $like = isset($m[1]) ? stripslashes($m[1]) : null;
                if ($sqlite) {
                    $q = "SELECT name AS \"Tables_in_db\" FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'" . ($like !== null ? ' AND name LIKE ' . $this->quote($like) : '') . ' ORDER BY name';
                } else {
                    $q = "SELECT table_name AS \"Tables_in_db\" FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'" . ($like !== null ? ' AND table_name LIKE ' . $this->quote($like) : '') . ' ORDER BY table_name';
                }
                return parent::query($q);
            }
            if (preg_match('/^SHOW\s+(?:FULL\s+)?(?:COLUMNS|FIELDS)\s+(?:FROM|IN)\s+[`"]?([\w$]+)[`"]?/i', $s, $m)) return $this->columnsOf($m[1]);
            return $this->empty();
        }
        if ($kw === 'DESCRIBE' || $kw === 'DESC') {
            if (preg_match('/^DESC(?:RIBE)?\s+[`"]?([\w$]+)[`"]?\s*$/i', $s, $m)) return $this->columnsOf($m[1]);
            return null;
        }
        return $this->empty();
    }

    private function columnsOf(string $table): \PDOStatement
    {
        $t = str_replace(["'", '"', '`'], '', $table);
        if ($this->dialect->isSqlite()) {
            return parent::query("SELECT name AS \"Field\", lower(type) AS \"Type\", CASE WHEN \"notnull\" = 1 THEN 'NO' ELSE 'YES' END AS \"Null\",
                CASE WHEN pk > 0 THEN 'PRI' ELSE '' END AS \"Key\", dflt_value AS \"Default\", '' AS \"Extra\" FROM pragma_table_info('$t')");
        }
        return parent::query("SELECT column_name AS \"Field\", data_type AS \"Type\", CASE is_nullable WHEN 'YES' THEN 'YES' ELSE 'NO' END AS \"Null\",
            '' AS \"Key\", column_default AS \"Default\", '' AS \"Extra\" FROM information_schema.columns
            WHERE table_schema = current_schema() AND table_name = '$t' ORDER BY ordinal_position");
    }

    /** Run DDL (possibly several statements). */
    private function ddl(string $sql): \PDOStatement
    {
        foreach (Ddl::translate($sql, $this->dialect->driver) as $stmt) parent::exec($stmt);
        $this->forgetSchema();
        return $this->empty();
    }

    private function prepareSql(string $sql): string
    {
        $t = $this->dialect->translate($sql);
        if ($this->dialect->calcFoundRows) $this->calcQueries[$t] = true;
        return $t;
    }

    public function noteExecuted(string $translated, array $params): void
    {
        if (isset($this->calcQueries[$translated])) $this->lastCalc = [$translated, array_values($params)];
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        if (($st = $this->intercept($query)) !== null) return $st;
        if (Ddl::isDdl($query)) return $this->ddl($query);
        $t = $this->prepareSql($query);
        $st = $fetchMode === null ? parent::query($t) : parent::query($t, $fetchMode, ...$fetchModeArgs);
        if (isset($this->calcQueries[$t])) $this->lastCalc = [$t, []];
        return $st;
    }

    #[\ReturnTypeWillChange]
    public function exec(string $statement): int|false
    {
        if ($this->intercept($statement) !== null) return 0;
        if (Ddl::isDdl($statement)) { $this->ddl($statement); return 0; }
        return parent::exec($this->prepareSql($statement));
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if (($st = $this->intercept($query)) !== null) return $st;
        if (Ddl::isDdl($query)) { $this->ddl($query); return parent::prepare('SELECT 1 WHERE 1 = 0'); }
        return parent::prepare($this->prepareSql($query), $options);
    }

    #[\ReturnTypeWillChange]
    public function lastInsertId(?string $name = null): string|false
    {
        try {
            return $name === null ? parent::lastInsertId() : parent::lastInsertId($name);
        } catch (\Throwable) {
            return '0';
        }
    }

    /** After bulk loads with explicit ids, move PostgreSQL sequences past the highest id. */
    public function resetSequences(): void
    {
        if (!$this->dialect->isPg()) return;
        $rows = parent::query("SELECT c.relname AS t, a.attname AS col, pg_get_serial_sequence(quote_ident(c.relname), a.attname) AS seq
            FROM pg_class c JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0
            WHERE c.relkind = 'r' AND c.relnamespace = current_schema()::regnamespace AND pg_get_serial_sequence(quote_ident(c.relname), a.attname) IS NOT NULL")->fetchAll();
        foreach ($rows as $r) {
            parent::exec('SELECT setval(' . $this->quote($r['seq']) . ', COALESCE((SELECT MAX("' . $r['col'] . '") FROM "' . $r['t'] . '"), 0) + 1, false)');
        }
    }
}
