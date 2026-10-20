<?php
/**
 * Kutubkhanah: translate the MySQL dialect that the SLiMS-derived code is written in
 * into SQLite or PostgreSQL. MySQL stays untouched.
 *
 * It works on tokens, so text inside string literals is never altered. Covered: identifier and string
 * quoting, backslash escapes, NOW/CURDATE/IFNULL/IF/CONCAT, GROUP_CONCAT, DATE_FORMAT, YEAR/MONTH/DAY,
 * DATEDIFF, DATE_ADD/DATE_SUB, UNIX_TIMESTAMP, LOCATE, LEFT/RIGHT, CAST types, LIMIT a,b, INSERT IGNORE,
 * ON DUPLICATE KEY UPDATE, REGEXP, case-insensitive LIKE, MATCH ... AGAINST (as LIKE terms),
 * SQL_CALC_FOUND_ROWS, TRUNCATE, SET/SHOW/USE statements.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

final class Dialect
{
    public const MYSQL = 'mysql';
    public const SQLITE = 'sqlite';
    public const PGSQL = 'pgsql';

    /** @var callable|null fn(string $table): array<int,array<int,string>> unique column sets of a table */
    private $uniqueKeys;
    private array $cache = [];

    /** Set by translate() when the query used SQL_CALC_FOUND_ROWS. */
    public bool $calcFoundRows = false;

    public function __construct(public readonly string $driver, ?callable $uniqueKeys = null)
    {
        $this->uniqueKeys = $uniqueKeys;
    }

    public static function normaliseDriver(string $d): string
    {
        $d = strtolower($d);
        return match (true) {
            in_array($d, ['pgsql', 'postgres', 'postgresql', 'pg'], true) => self::PGSQL,
            in_array($d, ['sqlite', 'sqlite3'], true) => self::SQLITE,
            default => self::MYSQL,
        };
    }

    public function isMysql(): bool { return $this->driver === self::MYSQL; }
    public function isPg(): bool { return $this->driver === self::PGSQL; }
    public function isSqlite(): bool { return $this->driver === self::SQLITE; }

    /** Translate one statement. */
    public function translate(string $sql): string
    {
        if ($this->isMysql()) return $sql;
        $this->calcFoundRows = false;
        $key = $sql;
        if (isset($this->cache[$key])) { [$out, $this->calcFoundRows] = $this->cache[$key]; return $out; }

        $sql = $this->multiTableDelete($sql);
        $tokens = self::tokenize($sql);
        $out = $this->run($tokens);
        if (count($this->cache) > 500) $this->cache = [];
        $this->cache[$key] = [$out, $this->calcFoundRows];
        return $out;
    }

    /** DELETE a FROM t a JOIN u ON … WHERE … (MySQL multi-table delete) → DELETE FROM t WHERE <row id> IN (SELECT …). */
    private function multiTableDelete(string $sql): string
    {
        if (!preg_match('/^\s*DELETE\s+(?:LOW_PRIORITY\s+|QUICK\s+|IGNORE\s+)*([`\w]+)\s+FROM\s+(.+)$/is', $sql, $m)) return $sql;
        $target = trim($m[1], '`');
        if (strcasecmp($target, 'FROM') === 0) return $sql;
        $rest = $m[2];
        if (!preg_match('/^([`\w]+)(?:\s+(?:AS\s+)?(?!(?:INNER|LEFT|RIGHT|CROSS|JOIN|WHERE|USING|ORDER|LIMIT)\b)([`\w]+))?/i', $rest, $t)) return $sql;
        $table = trim($t[1], '`');
        $alias = isset($t[2]) && $t[2] !== '' ? trim($t[2], '`') : $table;
        $rowid = $this->isSqlite() ? 'rowid' : 'ctid';
        return "DELETE FROM `$table` WHERE $rowid IN (SELECT `$alias`.$rowid FROM $rest)";
    }

    // ---- tokenizer -----------------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string}> [type, value]; types: ws str ident word num sym var */
    public static function tokenize(string $sql): array
    {
        $t = [];
        $n = strlen($sql);
        $i = 0;
        while ($i < $n) {
            $c = $sql[$i];
            if (ctype_space($c)) {
                $j = $i; while ($j < $n && ctype_space($sql[$j])) $j++;
                $t[] = ['ws', substr($sql, $i, $j - $i)]; $i = $j; continue;
            }
            if ($c === '-' && ($sql[$i + 1] ?? '') === '-' && (($sql[$i + 2] ?? ' ') === ' ' || ($sql[$i + 2] ?? '') === "\n")) {
                $j = strpos($sql, "\n", $i); $j = $j === false ? $n : $j; $t[] = ['ws', ' ']; $i = $j; continue;
            }
            if ($c === '#') { $j = strpos($sql, "\n", $i); $j = $j === false ? $n : $j; $t[] = ['ws', ' ']; $i = $j; continue; }
            if ($c === '/' && ($sql[$i + 1] ?? '') === '*') {
                $j = strpos($sql, '*/', $i + 2); $j = $j === false ? $n : $j + 2; $t[] = ['ws', ' ']; $i = $j; continue;
            }
            if ($c === "'" || $c === '"') {
                $q = $c; $j = $i + 1; $buf = '';
                while ($j < $n) {
                    $d = $sql[$j];
                    if ($d === '\\' && $j + 1 < $n) {
                        $e = $sql[$j + 1];
                        $buf .= match ($e) { 'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a", 'b' => "\x08", default => $e };
                        $j += 2; continue;
                    }
                    if ($d === $q) {
                        if (($sql[$j + 1] ?? '') === $q) { $buf .= $q; $j += 2; continue; }
                        break;
                    }
                    $buf .= $d; $j++;
                }
                $t[] = ['str', $buf]; $i = $j + 1; continue;
            }
            if ($c === '`') {
                $j = strpos($sql, '`', $i + 1); $j = $j === false ? $n : $j;
                $id = substr($sql, $i + 1, $j - $i - 1);
                // All-caps identifiers (e.g. `ID`) are folded so unquoted references match on PostgreSQL.
                if (preg_match('/^[A-Z][A-Z0-9_]*$/', $id)) $id = strtolower($id);
                $t[] = ['ident', $id]; $i = $j + 1; continue;
            }
            if ($c === '@') {
                $j = $i + 1; while ($j < $n && (ctype_alnum($sql[$j]) || $sql[$j] === '_' || $sql[$j] === '@')) $j++;
                $t[] = ['var', substr($sql, $i, $j - $i)]; $i = $j; continue;
            }
            if (ctype_digit($c) || ($c === '.' && ctype_digit($sql[$i + 1] ?? ''))) {
                $j = $i; while ($j < $n && (ctype_alnum($sql[$j]) || $sql[$j] === '.')) $j++;
                $t[] = ['num', substr($sql, $i, $j - $i)]; $i = $j; continue;
            }
            if (ctype_alpha($c) || $c === '_' || ord($c) > 127) {
                $j = $i; while ($j < $n && (ctype_alnum($sql[$j]) || $sql[$j] === '_' || $sql[$j] === '$' || ord($sql[$j]) > 127)) $j++;
                $t[] = ['word', substr($sql, $i, $j - $i)]; $i = $j; continue;
            }
            // multi-character symbols
            $two = substr($sql, $i, 2);
            if (in_array($two, ['<=', '>=', '<>', '!=', '||', '&&', ':=', '<<', '>>'], true)) { $t[] = ['sym', $two]; $i += 2; continue; }
            $t[] = ['sym', $c]; $i++;
        }
        return $t;
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function lit(string $s): string { return "'" . str_replace("'", "''", $s) . "'"; }

    private function ident(string $s): string { return '"' . str_replace('"', '""', $s) . '"'; }

    /** PostgreSQL words that cannot be used bare as names; the SLiMS schema uses some of them. */
    private const PG_QUOTE = ['user' => 1, 'order' => 1, 'group' => 1, 'limit' => 1, 'offset' => 1, 'end' => 1, 'both' => 1, 'check' => 1, 'default' => 1, 'collation' => 1, 'desc' => 0];

    private const ALIAS_KEYWORDS = ['index' => 1, 'key' => 1, 'user' => 1, 'order' => 1, 'group' => 1, 'table' => 1, 'values' => 1, 'column' => 1, 'check' => 1, 'default' => 1, 'limit' => 1, 'end' => 1];

    private static function isWord(array $t, string $w): bool
    {
        return $t[0] === 'word' && strcasecmp($t[1], $w) === 0;
    }

    /** Index of the next non-whitespace token at or after $i. */
    private static function skipWs(array $t, int $i): int
    {
        $n = count($t);
        while ($i < $n && $t[$i][0] === 'ws') $i++;
        return $i;
    }

    private static function prevNonWs(array $t, int $i): int
    {
        $i--;
        while ($i >= 0 && $t[$i][0] === 'ws') $i--;
        return $i;
    }

    /** Given the index of "(", return [index of matching ")", list of top-level argument token arrays]. */
    private static function args(array $t, int $open): array
    {
        $depth = 0; $args = [[]]; $n = count($t);
        for ($i = $open; $i < $n; $i++) {
            $tok = $t[$i];
            if ($tok[0] === 'sym' && $tok[1] === '(') { $depth++; if ($depth === 1) continue; }
            elseif ($tok[0] === 'sym' && $tok[1] === ')') { $depth--; if ($depth === 0) return [$i, $args]; }
            elseif ($tok[0] === 'sym' && $tok[1] === ',' && $depth === 1) { $args[] = []; continue; }
            $args[count($args) - 1][] = $tok;
        }
        return [$n - 1, $args];
    }

    private function render(array $tokens): string
    {
        return trim($this->run($tokens));
    }

    // ---- main pass -----------------------------------------------------------------------------

    private function run(array $t): string
    {
        $out = '';
        $n = count($t);
        $pgTail = '';           // text appended at the very end (PostgreSQL ON CONFLICT DO NOTHING)
        $lead = $this->leadWords($t);

        // Statements handled as a whole.
        if ($lead[0] === 'TRUNCATE') {
            $table = '';
            foreach ($t as $tok) if ($tok[0] === 'ident' || ($tok[0] === 'word' && !in_array(strtoupper($tok[1]), ['TRUNCATE', 'TABLE'], true))) $table = $tok[1];
            return $this->isPg() ? 'TRUNCATE TABLE ' . $this->ident($table) . ' RESTART IDENTITY'
                                 : 'DELETE FROM ' . $this->ident($table);
        }

        $i = 0;
        while ($i < $n) {
            [$type, $val] = $t[$i];

            switch ($type) {
                case 'str':
                    // AS 'Alias' is a column alias, not a value.
                    $pp = self::prevNonWs($t, $i);
                    if ($pp >= 0 && self::isWord($t[$pp], 'AS')) { $out .= $this->ident($val); $i++; continue 2; }
                    $out .= $this->lit($val); $i++; continue 2;
                case 'ident':
                    $out .= $this->ident($val); $i++; continue 2;
                case 'ws':
                    $out .= ' '; $i++; continue 2;
                case 'sym':
                    if ($val === '&&') { $out .= ' AND '; $i++; continue 2; }
                    if ($val === '||' ) { $out .= ' OR '; $i++; continue 2; }   // MySQL: logical OR
                    $out .= $val; $i++; continue 2;
                case 'num':
                case 'var':
                    $out .= $val; $i++; continue 2;
            }

            // words
            $u = strtoupper($val);
            $next = self::skipWs($t, $i + 1);
            $isCall = $next < $n && $t[$next][0] === 'sym' && $t[$next][1] === '(';
            $prev = self::prevNonWs($t, $i);
            $afterDot = $prev >= 0 && $t[$prev][0] === 'sym' && $t[$prev][1] === '.';

            if ($afterDot) { $out .= $val; $i++; continue; }

            // A keyword used as a table alias and then as "alias.column".
            if (!$isCall && $next < $n && $t[$next][0] === 'sym' && $t[$next][1] === '.' && isset(self::ALIAS_KEYWORDS[strtolower($val)])) {
                $out .= $this->ident(strtolower($val)); $i++; continue;
            }

            // SQL_CALC_FOUND_ROWS
            if ($u === 'SQL_CALC_FOUND_ROWS') { $this->calcFoundRows = true; $i++; continue; }
            // SQL_NO_CACHE etc.
            if (in_array($u, ['SQL_NO_CACHE', 'SQL_CACHE', 'STRAIGHT_JOIN', 'HIGH_PRIORITY', 'LOW_PRIORITY', 'DELAYED'], true)) { $i++; continue; }

            // INSERT IGNORE / REPLACE INTO
            if ($u === 'IGNORE' && $prev >= 0 && self::isWord($t[$prev], 'INSERT')) {
                if ($this->isSqlite()) $out .= 'OR IGNORE'; else $pgTail = ' ON CONFLICT DO NOTHING';
                $i++; continue;
            }

            // ON DUPLICATE KEY UPDATE
            if ($u === 'ON' && ($j = self::skipWs($t, $i + 1)) < $n && self::isWord($t[$j], 'DUPLICATE')) {
                $k = self::skipWs($t, $j + 1); $k = self::skipWs($t, $k + 1);   // KEY, UPDATE
                $k = self::skipWs($t, $k + 1);
                $rest = array_slice($t, $k);
                $out = rtrim($out) . ' ON CONFLICT ' . $this->conflictTarget($t) . ' DO UPDATE SET ' . $this->convertUpsertAssignments($rest);
                return rtrim($out, "; \t\r\n") . $pgTail;
            }

            // COLLATE xxx / CHARACTER SET xxx
            if ($u === 'COLLATE') { $i = self::skipWs($t, $i + 1) + 1; continue; }
            if ($u === 'BINARY' && !$isCall) { $i++; continue; }

            // LIMIT offset, count
            if ($u === 'LIMIT') {
                $a = self::skipWs($t, $i + 1);
                $b = $a < $n ? self::skipWs($t, $a + 1) : $n;
                if ($b < $n && $t[$b][0] === 'sym' && $t[$b][1] === ',') {
                    $c = self::skipWs($t, $b + 1);
                    $out .= 'LIMIT ' . $this->value($t[$c]) . ' OFFSET ' . $this->value($t[$a]);
                    $i = $c + 1; continue;
                }
                $out .= 'LIMIT'; $i++; continue;
            }

            // MATCH (cols) AGAINST ('terms' [IN BOOLEAN MODE])
            if ($u === 'MATCH' && $isCall) {
                [$close, $cols] = self::args($t, $next);
                $a = self::skipWs($t, $close + 1);
                if ($a < $n && self::isWord($t[$a], 'AGAINST')) {
                    $ap = self::skipWs($t, $a + 1);
                    [$aclose, $aargs] = self::args($t, $ap);
                    $out .= $this->matchAgainst($cols, $aargs[0] ?? []);
                    $i = $aclose + 1; continue;
                }
            }

            // LIKE → ILIKE on PostgreSQL (MySQL's LIKE ignores case)
            if ($u === 'LIKE' && $this->isPg()) { $out .= 'ILIKE'; $i++; continue; }

            // REGEXP / RLIKE
            if (($u === 'REGEXP' || $u === 'RLIKE') && !$isCall) {
                $neg = $prev >= 0 && self::isWord($t[$prev], 'NOT');
                if ($this->isPg()) {
                    if ($neg) { $out = preg_replace('/NOT\s*$/i', '', rtrim($out)) . ' '; $out .= '!~* '; } else $out .= '~* ';
                } else $out .= 'REGEXP';
                $i++; continue;
            }

            if ($isCall) {
                $handled = $this->func($u, $val, $t, $i, $next, $out);
                if ($handled !== null) { $i = $handled; continue; }
            }

            // bare no-argument functions and keywords
            switch ($u) {
                case 'CURDATE':
                case 'CURRENT_DATE':
                    if ($isCall) {} else { $out .= $this->isSqlite() ? "date('now','localtime')" : 'CURRENT_DATE'; $i++; continue 2; }
                    break;
                case 'UNSIGNED':
                    $out .= 'INTEGER'; $i++; continue 2;
            }

            // PostgreSQL: reserved words used as bare names (the "user" table) must be quoted.
            if ($this->isPg() && !$isCall && in_array(strtolower($val), ['user', 'collation'], true)) {
                $out .= '"' . strtolower($val) . '"'; $i++; continue;
            }

            // Alias case: pg folds unquoted aliases to lower case; keep the case the code asks for.
            if ($this->isPg() && $u === 'AS' && ($j = self::skipWs($t, $i + 1)) < $n && $t[$j][0] === 'word'
                && $t[$j][1] !== strtolower($t[$j][1])
                && !in_array(strtoupper($t[$j][1]), ['SIGNED', 'UNSIGNED', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'INTEGER', 'INT', 'BINARY', 'TEXT', 'NUMERIC', 'VARCHAR', 'TIMESTAMP', 'BIGINT'], true)) {
                $out .= 'AS ' . $this->ident($t[$j][1]); $i = $j + 1; continue;
            }

            $out .= $val; $i++;
        }

        return rtrim($out, "; \t\r\n") . $pgTail;
    }

    private function value(array $tok): string
    {
        return match ($tok[0]) { 'str' => $this->lit($tok[1]), default => $tok[1] };
    }

    /** First word(s) of the statement, upper-cased. */
    private function leadWords(array $t): array
    {
        $w = [];
        foreach ($t as $tok) {
            if ($tok[0] === 'ws') continue;
            $w[] = $tok[0] === 'word' ? strtoupper($tok[1]) : '';
            if (count($w) >= 2) break;
        }
        return $w + [0 => '', 1 => ''];
    }

    // ---- functions ---------------------------------------------------------------------------------

    /** @return int|null index after the call when handled, null to fall through */
    private function func(string $u, string $raw, array $t, int $i, int $open, string &$out): ?int
    {
        [$close, $args] = self::args($t, $open);
        $a = array_map(fn($x) => $this->render($x), $args);
        $sqlite = $this->isSqlite();
        $pg = $this->isPg();
        $emit = function (string $s) use (&$out, $close) { $out .= $s; return $close + 1; };

        switch ($u) {
            case 'NOW':
            case 'CURRENT_TIMESTAMP':
            case 'SYSDATE':
            case 'LOCALTIME':
                if (count($args) === 1 && $args[0] === []) return $emit($sqlite ? "datetime('now','localtime')" : 'LOCALTIMESTAMP(0)');
                return null;
            case 'CURDATE':
                return $emit($sqlite ? "date('now','localtime')" : 'CURRENT_DATE');
            case 'CURTIME':
                return $emit($sqlite ? "time('now','localtime')" : 'LOCALTIME(0)');
            case 'IFNULL':
                return $pg ? $emit('COALESCE(' . implode(', ', $a) . ')') : null;
            case 'IF':
                if (count($a) === 3) {
                    // PostgreSQL refuses to mix a string literal with a number in one CASE; MySQL coerces.
                    $isLit = fn($x) => (bool)preg_match("/^\s*'.*'\s*$/s", $x);
                    if ($this->driver === 'pgsql' && $isLit($a[1]) !== $isLit($a[2])) {
                        if (!$isLit($a[1])) $a[1] = "CAST({$a[1]} AS TEXT)"; else $a[2] = "CAST({$a[2]} AS TEXT)";
                    }
                    return $emit("(CASE WHEN {$a[0]} THEN {$a[1]} ELSE {$a[2]} END)");
                }
                return null;
            case 'CONCAT':
                return $emit('(' . implode(' || ', array_map(fn($x) => "COALESCE(CAST($x AS TEXT), '')", $a)) . ')');
            case 'CONCAT_WS':
                if ($sqlite) {
                    $sep = array_shift($a);
                    return $emit('(' . implode(" || $sep || ", array_map(fn($x) => "COALESCE(CAST($x AS TEXT), '')", $a)) . ')');
                }
                return null;
            case 'GROUP_CONCAT':
                return $emit($this->groupConcat($args));
            case 'DATE_FORMAT':
                if (count($a) === 2) return $emit($this->dateFormat($a[0], $args[1]));
                return null;
            case 'YEAR':
            case 'MONTH':
            case 'DAY':
            case 'DAYOFMONTH':
            case 'HOUR':
            case 'MINUTE':
            case 'SECOND':
                $f = ['YEAR' => '%Y', 'MONTH' => '%m', 'DAY' => '%d', 'DAYOFMONTH' => '%d', 'HOUR' => '%H', 'MINUTE' => '%M', 'SECOND' => '%S'][$u];
                if ($sqlite) return $emit("CAST(strftime('$f', {$a[0]}) AS INTEGER)");
                return $emit('CAST(EXTRACT(' . ($u === 'DAYOFMONTH' ? 'DAY' : $u) . " FROM CAST({$a[0]} AS TIMESTAMP)) AS INTEGER)");
            case 'DATE':
                return $sqlite ? $emit("date({$a[0]})") : $emit("CAST({$a[0]} AS DATE)");
            case 'TO_DAYS':
                return $sqlite ? $emit("CAST(julianday({$a[0]}) - 1721059.5 AS INTEGER)") : $emit("(CAST({$a[0]} AS DATE) - DATE '0001-01-01' + 366)");
            case 'GREATEST':
                return $sqlite ? $emit('max(' . implode(', ', $a) . ')') : null;
            case 'LEAST':
                return $sqlite ? $emit('min(' . implode(', ', $a) . ')') : null;
            case 'DAYOFWEEK':
                return $sqlite ? $emit("(CAST(strftime('%w', {$a[0]}) AS INTEGER) + 1)") : $emit("(CAST(EXTRACT(DOW FROM CAST({$a[0]} AS TIMESTAMP)) AS INTEGER) + 1)");
            case 'DAYOFYEAR':
                return $sqlite ? $emit("CAST(strftime('%j', {$a[0]}) AS INTEGER)") : $emit("CAST(EXTRACT(DOY FROM CAST({$a[0]} AS TIMESTAMP)) AS INTEGER)");
            case 'LAST_DAY':
                return $sqlite ? $emit("date({$a[0]}, 'start of month', '+1 month', '-1 day')") : $emit("CAST(date_trunc('month', CAST({$a[0]} AS DATE)) + INTERVAL '1 month - 1 day' AS DATE)");
            case 'TIMESTAMPDIFF':
                if (count($a) === 3) {
                    $unit = strtolower(trim($a[0]));
                    if ($sqlite) {
                        $div = ['second' => 1, 'minute' => 60, 'hour' => 3600, 'day' => 86400, 'week' => 604800][$unit] ?? 86400;
                        return $emit("CAST((julianday({$a[2]}) - julianday({$a[1]})) * 86400 / $div AS INTEGER)");
                    }
                    $div = ['second' => 1, 'minute' => 60, 'hour' => 3600, 'day' => 86400, 'week' => 604800][$unit] ?? 86400;
                    return $emit("CAST(EXTRACT(EPOCH FROM (CAST({$a[2]} AS TIMESTAMP) - CAST({$a[1]} AS TIMESTAMP))) / $div AS BIGINT)");
                }
                return null;
            case 'DATEDIFF':
                return $sqlite ? $emit("CAST(julianday({$a[0]}) - julianday({$a[1]}) AS INTEGER)")
                               : $emit("(CAST({$a[0]} AS DATE) - CAST({$a[1]} AS DATE))");
            case 'DATE_ADD':
            case 'ADDDATE':
            case 'DATE_SUB':
            case 'SUBDATE':
                if (count($args) === 2) return $emit($this->dateAdd($a[0], $args[1], in_array($u, ['DATE_SUB', 'SUBDATE'], true)));
                return null;
            case 'UNIX_TIMESTAMP':
                if (count($a) === 0 || $a[0] === '') return $emit($sqlite ? "CAST(strftime('%s','now') AS INTEGER)" : 'CAST(EXTRACT(EPOCH FROM NOW()) AS BIGINT)');
                return $emit($sqlite ? "CAST(strftime('%s', {$a[0]}) AS INTEGER)" : "CAST(EXTRACT(EPOCH FROM CAST({$a[0]} AS TIMESTAMP)) AS BIGINT)");
            case 'FROM_UNIXTIME':
                return $emit($sqlite ? "datetime({$a[0]}, 'unixepoch', 'localtime')" : "TO_TIMESTAMP({$a[0]})");
            case 'RAND':
                return $emit($sqlite ? '(random() / 18446744073709551616.0 + 0.5)' : 'random()');
            case 'LAST_INSERT_ID':
                return $emit($sqlite ? 'last_insert_rowid()' : 'lastval()');
            case 'LOCATE':
                return $emit($sqlite ? "instr({$a[1]}, {$a[0]})" : "strpos({$a[1]}, {$a[0]})");
            case 'SUBSTRING':
            case 'SUBSTR':
                if ($pg && count($a) >= 2 && !preg_match('/\bFROM\b/i', $a[0])) return $emit('substr(CAST(' . $a[0] . ' AS TEXT), ' . implode(', ', array_slice($a, 1)) . ')');
                return null;
            case 'LEFT':
                return $sqlite ? $emit("substr({$a[0]}, 1, {$a[1]})") : null;
            case 'RIGHT':
                return $sqlite ? $emit("substr({$a[0]}, -({$a[1]}))") : null;
            case 'LPAD':
                return $sqlite ? $emit("substr(replace(hex(zeroblob({$a[1]})), '00', {$a[2]}) || {$a[0]}, -({$a[1]}))") : null;
            case 'CHAR_LENGTH':
            case 'CHARACTER_LENGTH':
                return $emit("length({$a[0]})");
            case 'FIND_IN_SET':
                return $emit($sqlite ? "(instr(',' || {$a[1]} || ',', ',' || {$a[0]} || ',') > 0)" : "(position(',' || {$a[0]} || ',' IN ',' || {$a[1]} || ',') > 0)");
            case 'CAST':
                // CAST(x AS UNSIGNED|SIGNED|CHAR) → portable types
                if (count($args) === 1) {
                    $s = $this->render($args[0]);
                    $s = preg_replace(['/\bAS\s+(UNSIGNED|SIGNED)(\s+INTEGER)?\s*$/i', '/\bAS\s+CHAR(\(\d+\))?\s*$/i', '/\bAS\s+DATETIME\s*$/i'],
                        ['AS INTEGER', 'AS TEXT', $pg ? 'AS TIMESTAMP' : 'AS TEXT'], $s);
                    $s = preg_replace('/\bAS\s+DATE\s*$/i', 'AS DATE', $s);
                    return $emit('CAST(' . $s . ')');
                }
                return null;
            case 'MD5':
                return $pg ? null : null;
            case 'REPLACE':
                return null;
            case 'FOUND_ROWS':
                return null;
            case 'DATABASE':
                return $emit($pg ? 'current_database()' : "'main'");
            case 'VERSION':
                return $emit($pg ? 'version()' : 'sqlite_version()');
            case 'TRIM':
                return null;
            case 'CHAR':
                return $emit($pg ? "chr({$a[0]})" : "char({$a[0]})");
            case 'MAKEDATE':
            case 'WEEK':
            case 'DAYOFWEEK':
                return null;
        }
        return null;
    }

    private function groupConcat(array $args): string
    {
        // Pull "DISTINCT", "ORDER BY ..." and "SEPARATOR 'x'" out of the single argument.
        $toks = $args[0] ?? [];
        $sep = ',';
        $distinct = false;
        $orderBy = '';
        $expr = [];
        $n = count($toks);
        for ($i = 0; $i < $n; $i++) {
            $tk = $toks[$i];
            if (self::isWord($tk, 'DISTINCT') && !$expr) { $distinct = true; continue; }
            if (self::isWord($tk, 'SEPARATOR')) {
                $j = self::skipWs($toks, $i + 1);
                $sep = $toks[$j][1] ?? ',';
                $i = $j; continue;
            }
            if (self::isWord($tk, 'ORDER')) {
                $j = self::skipWs($toks, $i + 1);
                if (isset($toks[$j]) && self::isWord($toks[$j], 'BY')) {
                    $rest = [];
                    for ($k = $j + 1; $k < $n; $k++) {
                        if (self::isWord($toks[$k], 'SEPARATOR')) { $q = self::skipWs($toks, $k + 1); $sep = $toks[$q][1] ?? ','; $k = $q; continue; }
                        $rest[] = $toks[$k];
                    }
                    $orderBy = $this->render($rest);
                    break;
                }
            }
            $expr[] = $tk;
        }
        $e = $this->render($expr);
        if ($this->isSqlite()) {
            if (!$distinct) return 'group_concat(' . $e . ', ' . $this->lit($sep) . ')';
            return $sep === ',' ? 'group_concat(DISTINCT ' . $e . ')' : "replace(group_concat(DISTINCT $e), ',', " . $this->lit($sep) . ')';
        }
        // PostgreSQL: with DISTINCT the ORDER BY must be the aggregated expression, so ordering is dropped.
        return 'string_agg(' . ($distinct ? 'DISTINCT ' : '') . 'CAST(' . $e . ' AS TEXT), ' . $this->lit($sep) . (!$distinct && $orderBy !== '' ? ' ORDER BY ' . $orderBy : '') . ')';
    }

    private const FMT_SQLITE = ['%Y' => '%Y', '%y' => '%y', '%m' => '%m', '%d' => '%d', '%H' => '%H', '%i' => '%M', '%s' => '%S', '%S' => '%S', '%e' => '%d', '%j' => '%j', '%T' => '%H:%M:%S', '%%' => '%%'];
    private const FMT_PG = ['%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%d' => 'DD', '%H' => 'HH24', '%h' => 'HH12', '%i' => 'MI', '%s' => 'SS', '%S' => 'SS', '%M' => 'FMMonth', '%b' => 'Mon', '%D' => 'FMDDth', '%e' => 'FMDD', '%j' => 'DDD', '%W' => 'FMDay', '%a' => 'Dy', '%p' => 'AM', '%T' => 'HH24:MI:SS'];

    private function dateFormat(string $expr, array $fmtTokens): string
    {
        $fmt = '';
        foreach ($fmtTokens as $tk) if ($tk[0] === 'str') { $fmt = $tk[1]; break; }
        if ($fmt === '') return $this->isSqlite() ? "strftime('%Y-%m-%d', $expr)" : "TO_CHAR(CAST($expr AS TIMESTAMP), 'YYYY-MM-DD')";
        if ($this->isSqlite()) {
            return 'strftime(' . $this->lit(strtr($fmt, self::FMT_SQLITE)) . ', ' . $expr . ')';
        }
        return 'TO_CHAR(CAST(' . $expr . ' AS TIMESTAMP), ' . $this->lit(strtr($fmt, self::FMT_PG)) . ')';
    }

    private function dateAdd(string $date, array $intervalTokens, bool $sub): string
    {
        // INTERVAL <expr> <UNIT>
        $toks = array_values(array_filter($intervalTokens, fn($x) => $x[0] !== 'ws'));
        if ($toks && self::isWord($toks[0], 'INTERVAL')) array_shift($toks);
        $unit = strtolower((string)(array_pop($toks)[1] ?? 'day'));
        $n = $this->render($toks);
        $sign = $sub ? '-' : '+';
        $unit = rtrim($unit, 's');
        if ($this->isSqlite()) {
            $u = $unit === 'week' ? 'day' : $unit;
            $mul = $unit === 'week' ? ' * 7' : '';
            return "datetime($date, '$sign' || ($n$mul) || ' $u')";
        }
        return "($date $sign ($n) * INTERVAL '1 $unit')";
    }

    // ---- upsert / full-text ------------------------------------------------------------------------

    /** "(col, col)" for ON CONFLICT, from the insert's column list and the table's unique keys. */
    private function conflictTarget(array $t): string
    {
        $table = '';
        $cols = [];
        $n = count($t);
        for ($i = 0; $i < $n; $i++) {
            if (self::isWord($t[$i], 'INTO')) {
                $j = self::skipWs($t, $i + 1);
                $table = $t[$j][1] ?? '';
                $k = self::skipWs($t, $j + 1);
                if (isset($t[$k]) && $t[$k][0] === 'sym' && $t[$k][1] === '(') {
                    [, $list] = self::args($t, $k);
                    foreach ($list as $c) { $c = array_values(array_filter($c, fn($x) => $x[0] !== 'ws')); $cols[] = strtolower($c[0][1] ?? ''); }
                }
                break;
            }
        }
        $sets = $this->uniqueKeys && $table !== '' ? ($this->uniqueKeys)(strtolower($table)) : [];
        foreach ($sets as $set) {
            if (!array_diff($set, $cols)) return '(' . implode(', ', array_map([$this, 'ident'], $set)) . ')';
        }
        if ($sets) return '(' . implode(', ', array_map([$this, 'ident'], $sets[0])) . ')';
        return '';
    }

    private function convertUpsertAssignments(array $rest): string
    {
        $out = '';
        $n = count($rest);
        $prefix = $this->isSqlite() ? 'excluded.' : 'EXCLUDED.';
        for ($i = 0; $i < $n; $i++) {
            $tk = $rest[$i];
            if (self::isWord($tk, 'VALUES')) {
                $j = self::skipWs($rest, $i + 1);
                if (isset($rest[$j]) && $rest[$j][0] === 'sym' && $rest[$j][1] === '(') {
                    [$close, $a] = self::args($rest, $j);
                    $out .= $prefix . $this->render($a[0]);
                    $i = $close; continue;
                }
            }
            $out .= $this->run([$tk]);
        }
        return trim($out);
    }

    /** MATCH (cols) AGAINST ('words') → every word must appear in at least one column. */
    private function matchAgainst(array $colArgs, array $againstTokens): string
    {
        $cols = array_map(fn($c) => $this->render($c), $colArgs);
        $str = '';
        $paramTokens = [];
        foreach ($againstTokens as $tk) {
            if ($tk[0] === 'str') { $str = $tk[1]; $paramTokens[] = $tk; }
            elseif (self::isWord($tk, 'IN')) break;
            elseif ($tk[0] !== 'ws') $paramTokens[] = $tk;
        }
        $like = $this->isPg() ? 'ILIKE' : 'LIKE';
        if ($str === '' || count($paramTokens) !== 1 || $paramTokens[0][0] !== 'str') {
            // bound parameter or expression: match on the whole phrase
            $arg = $this->render($againstTokens ? array_slice($againstTokens, 0, $this->cutAtIn($againstTokens)) : []);
            $parts = [];
            foreach ($cols as $c) $parts[] = "$c $like ('%' || $arg || '%')";
            return '(' . implode(' OR ', $parts) . ')';
        }
        $must = []; $none = [];
        preg_match_all('/([+\-~]?)("([^"]+)"|\S+)/u', $str, $m, PREG_SET_ORDER);
        foreach ($m as $g) {
            $word = ($g[3] ?? '') !== '' ? $g[3] : trim($g[2], '"');
            $word = rtrim(ltrim($word, '()'), '*()');
            if ($word === '') continue;
            $esc = str_replace(['%', '_'], ['\\%', '\\_'], $word);
            $cond = '(' . implode(' OR ', array_map(fn($c) => "$c $like " . $this->lit('%' . $esc . '%') . " ESCAPE '\\'", $cols)) . ')';
            if ($g[1] === '-') $none[] = 'NOT ' . $cond; else $must[] = $cond;
        }
        $all = array_merge($must, $none);
        return $all ? '(' . implode(' AND ', $all) . ')' : '(1=1)';
    }

    private function cutAtIn(array $tokens): int
    {
        foreach ($tokens as $i => $tk) if (self::isWord($tk, 'IN')) return $i;
        return count($tokens);
    }
}
