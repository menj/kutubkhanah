<?php
/**
 * Kutubkhanah: turn the MySQL CREATE TABLE / ALTER TABLE statements of the schema into SQLite or
 * PostgreSQL. Engine, charset and collation clauses go; AUTO_INCREMENT becomes SERIAL (PostgreSQL) or
 * INTEGER PRIMARY KEY AUTOINCREMENT (SQLite); KEY clauses become CREATE INDEX; ENUM becomes VARCHAR;
 * FULLTEXT keys are skipped (search uses LIKE terms on these databases).
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

final class Ddl
{
    /** @return string[] statements for $driver; MySQL statements pass through unchanged. */
    public static function translate(string $sql, string $driver): array
    {
        if ($driver === Dialect::MYSQL) return [$sql];
        $head = strtoupper(ltrim(preg_replace('/\s+/', ' ', substr(ltrim($sql), 0, 40))));
        if (str_starts_with($head, 'CREATE TABLE')) return self::createTable($sql, $driver);
        if (str_starts_with($head, 'ALTER TABLE')) return self::alterTable($sql, $driver);
        if (preg_match('/^(DROP|RENAME) /', $head)) return [(new Dialect($driver))->translate($sql)];
        return [(new Dialect($driver))->translate($sql)];
    }

    public static function isDdl(string $sql): bool
    {
        return (bool)preg_match('/^\s*(CREATE\s+TABLE|ALTER\s+TABLE)\b/i', $sql);
    }

    // ---- CREATE TABLE ------------------------------------------------------------------------------

    private static function createTable(string $sql, string $driver): array
    {
        $t = Dialect::tokenize($sql);
        $n = count($t);
        $i = 0;
        // CREATE TABLE [IF NOT EXISTS] name (
        $table = '';
        $ifNot = false;
        for (; $i < $n; $i++) {
            $tk = $t[$i];
            if ($tk[0] === 'word' && strcasecmp($tk[1], 'IF') === 0) $ifNot = true;
            if ($tk[0] === 'ident' || ($tk[0] === 'word' && !in_array(strtoupper($tk[1]), ['CREATE', 'TABLE', 'IF', 'NOT', 'EXISTS', 'TEMPORARY'], true))) { $table = $tk[1]; $i++; break; }
        }
        while ($i < $n && !($t[$i][0] === 'sym' && $t[$i][1] === '(')) $i++;
        [$close, $items] = self::splitTop($t, $i);

        $cols = [];
        $pk = [];
        $indexes = [];
        $serialCol = null;
        foreach ($items as $item) {
            $item = array_values(array_filter($item, fn($x) => $x[0] !== 'ws'));
            if (!$item) continue;
            $w0 = strtoupper($item[0][1]);
            $w1 = strtoupper($item[1][1] ?? '');
            if ($item[0][0] === 'word' && $w0 === 'PRIMARY') { $pk = self::keyColumns($item); continue; }
            if ($item[0][0] === 'word' && ($w0 === 'UNIQUE' || $w0 === 'KEY' || $w0 === 'INDEX' || $w0 === 'FULLTEXT' || $w0 === 'SPATIAL')) {
                if ($w0 === 'FULLTEXT' || $w0 === 'SPATIAL') continue;
                $unique = $w0 === 'UNIQUE';
                $name = '';
                foreach (array_slice($item, 1) as $tk) {
                    if ($tk[0] === 'sym') break;
                    if (in_array(strtoupper($tk[1]), ['KEY', 'INDEX'], true)) continue;
                    $name = $tk[1];
                }
                $indexes[] = ['name' => $name, 'unique' => $unique, 'cols' => self::keyColumns($item)];
                continue;
            }
            if ($item[0][0] === 'word' && in_array($w0, ['CONSTRAINT', 'FOREIGN', 'CHECK'], true)) continue;
            $col = self::column($item, $driver);
            if ($col['auto']) $serialCol = $col['name'];
            $cols[] = $col;
        }

        // Single-column auto-increment primary key.
        $lines = [];
        foreach ($cols as $c) {
            if ($c['auto'] && $driver === Dialect::SQLITE) {
                $lines[] = '"' . $c['name'] . '" INTEGER PRIMARY KEY AUTOINCREMENT';
                $pk = [];
                continue;
            }
            $lines[] = $c['sql'];
        }
        // PostgreSQL only treats columns as functionally dependent (GROUP BY) through a real primary key,
        // so a table whose only identity is a unique key on NOT NULL columns gets it promoted.
        if (!$pk && $driver === Dialect::PGSQL) {
            foreach ($indexes as $k => $ix) {
                if (!$ix['unique'] || !$ix['cols']) continue;
                $ok = true;
                foreach ($ix['cols'] as $cn) {
                    $ok = $ok && (bool)array_filter($cols, fn($c) => $c['name'] === $cn && str_ends_with($c['sql'], ' NOT NULL'));
                }
                if ($ok) { $pk = $ix['cols']; unset($indexes[$k]); break; }
            }
        }
        if ($pk) $lines[] = 'PRIMARY KEY (' . implode(', ', array_map(fn($c) => '"' . $c . '"', $pk)) . ')';

        $out = ['CREATE TABLE ' . ($ifNot ? 'IF NOT EXISTS ' : '') . '"' . $table . '" (' . "\n  " . implode(",\n  ", $lines) . "\n)"];
        foreach ($indexes as $ix) {
            if (!$ix['cols']) continue;
            $name = $table . '_' . ($ix['name'] !== '' ? $ix['name'] : implode('_', $ix['cols']));
            $out[] = 'CREATE ' . ($ix['unique'] ? 'UNIQUE ' : '') . 'INDEX IF NOT EXISTS "' . $name . '" ON "' . $table . '" ('
                . implode(', ', array_map(fn($c) => '"' . $c . '"', $ix['cols'])) . ')';
        }
        // MySQL numbers a row when NULL is inserted into an AUTO_INCREMENT column; PostgreSQL needs a trigger for that.
        if ($driver === Dialect::PGSQL && $serialCol !== null) {
            $out[] = self::AUTOID_FUNCTION;
            $out[] = 'DROP TRIGGER IF EXISTS "' . $table . '_autoid" ON "' . $table . '"';
            $out[] = 'CREATE TRIGGER "' . $table . '_autoid" BEFORE INSERT ON "' . $table . '" FOR EACH ROW EXECUTE FUNCTION kk_autoid(\'' . $serialCol . '\')';
        }
        return $out;
    }

    private const AUTOID_FUNCTION = 'CREATE OR REPLACE FUNCTION kk_autoid() RETURNS trigger AS $$
DECLARE col text := TG_ARGV[0];
BEGIN
  IF (to_jsonb(NEW) ->> col) IS NULL THEN
    NEW := jsonb_populate_record(NEW, jsonb_build_object(col, nextval(pg_get_serial_sequence(quote_ident(TG_TABLE_NAME), col))));
  END IF;
  RETURN NEW;
END $$ LANGUAGE plpgsql';

    /** Split the parenthesised body starting at $open into top-level comma separated token lists. */
    private static function splitTop(array $t, int $open): array
    {
        $depth = 0; $items = [[]]; $n = count($t);
        for ($i = $open; $i < $n; $i++) {
            $tk = $t[$i];
            if ($tk[0] === 'sym' && $tk[1] === '(') { $depth++; if ($depth === 1) continue; }
            elseif ($tk[0] === 'sym' && $tk[1] === ')') { $depth--; if ($depth === 0) return [$i, $items]; }
            elseif ($tk[0] === 'sym' && $tk[1] === ',' && $depth === 1) { $items[] = []; continue; }
            $items[count($items) - 1][] = $tk;
        }
        return [$n - 1, $items];
    }

    /** Column names inside the first parenthesis of a key definition. */
    private static function keyColumns(array $item): array
    {
        $cols = [];
        $depth = 0;
        $expectName = true;
        foreach ($item as $tk) {
            if ($tk[0] === 'sym' && $tk[1] === '(') { $depth++; $expectName = true; continue; }
            if ($tk[0] === 'sym' && $tk[1] === ')') { $depth--; if ($depth === 0 && $cols) break; continue; }
            if ($depth === 1 && $tk[0] === 'sym' && $tk[1] === ',') { $expectName = true; continue; }
            if ($depth === 1 && $expectName && ($tk[0] === 'ident' || $tk[0] === 'word')) { $cols[] = $tk[1]; $expectName = false; }
        }
        return $cols;
    }

    /** One column definition → ['name', 'sql', 'auto']. */
    private static function column(array $item, string $driver): array
    {
        $name = $item[0][1];
        $i = 1;
        $type = strtolower($item[$i][1] ?? 'text');
        $i++;
        $args = [];
        if (isset($item[$i]) && $item[$i][0] === 'sym' && $item[$i][1] === '(') {
            $depth = 0;
            for (; $i < count($item); $i++) {
                $tk = $item[$i];
                if ($tk[0] === 'sym' && $tk[1] === '(') { $depth++; continue; }
                if ($tk[0] === 'sym' && $tk[1] === ')') { $depth--; if ($depth === 0) { $i++; break; } continue; }
                if ($tk[0] === 'sym' && $tk[1] === ',') continue;
                $args[] = $tk[1];
            }
        }
        $notNull = false; $default = null; $hasDefault = false; $auto = false; $unsigned = false;
        for (; $i < count($item); $i++) {
            $tk = $item[$i];
            $w = strtoupper($tk[1]);
            if ($tk[0] !== 'word') continue;
            switch ($w) {
                case 'NOT': if (strtoupper($item[$i + 1][1] ?? '') === 'NULL') { $notNull = true; $i++; } break;
                case 'NULL': $notNull = false; break;
                case 'UNSIGNED': $unsigned = true; break;
                case 'AUTO_INCREMENT': $auto = true; break;
                case 'DEFAULT':
                    $i++;
                    $d = $item[$i] ?? null;
                    if ($d === null) break;
                    $hasDefault = true;
                    if ($d[0] === 'str') $default = ['str', $d[1]];
                    elseif ($d[0] === 'num') $default = ['num', $d[1]];
                    elseif (strtoupper($d[1]) === 'NULL') $default = ['null', ''];
                    elseif (str_starts_with(strtoupper($d[1]), 'CURRENT_TIMESTAMP')) {
                        $default = ['ts', ''];
                        if (($item[$i + 1][1] ?? '') === '(') { $i += 2; if (($item[$i][1] ?? '') === ')') {} }
                    }
                    elseif ($d[0] === 'sym' && $d[1] === '-' ) { $i++; $default = ['num', '-' . ($item[$i][1] ?? '0')]; }
                    else $default = ['str', $d[1]];
                    break;
                case 'ON': // ON UPDATE CURRENT_TIMESTAMP
                    if (strtoupper($item[$i + 1][1] ?? '') === 'UPDATE') { $i += 2; if (($item[$i + 1][1] ?? '') === '(') { $i += 2; } }
                    break;
                case 'COMMENT': $i++; break;
                case 'CHARACTER': $i += 2; break;
                case 'CHARSET': $i++; break;
                case 'COLLATE': $i++; break;
            }
        }

        $pg = $driver === Dialect::PGSQL;
        $kind = 'text';
        $sqlType = 'TEXT';
        switch ($type) {
            case 'tinyint': case 'smallint': case 'year': $kind = 'int'; $sqlType = ($pg && $auto) ? 'SERIAL' : ($pg ? 'SMALLINT' : 'INTEGER'); break;
            case 'int': case 'integer': case 'mediumint': $kind = 'int'; $sqlType = $pg ? ($auto ? 'SERIAL' : 'INTEGER') : 'INTEGER'; break;
            case 'bigint': $kind = 'int'; $sqlType = $pg ? ($auto ? 'BIGSERIAL' : 'BIGINT') : 'INTEGER'; break;
            case 'decimal': case 'numeric': $kind = 'num'; $sqlType = 'NUMERIC' . ($args ? '(' . implode(',', array_slice($args, 0, 2)) . ')' : ''); break;
            case 'float': $kind = 'num'; $sqlType = $pg ? 'REAL' : 'REAL'; break;
            case 'double': case 'real': $kind = 'num'; $sqlType = $pg ? 'DOUBLE PRECISION' : 'REAL'; break;
            case 'datetime': case 'timestamp': $kind = 'datetime'; $sqlType = $pg ? 'TIMESTAMP' : 'DATETIME'; break;
            case 'date': $kind = 'date'; $sqlType = 'DATE'; break;
            case 'time': $kind = 'date'; $sqlType = 'TIME'; break;
            case 'varchar': case 'char': $kind = 'text'; $sqlType = ($args && ctype_digit((string)$args[0]) ? ($type === 'char' ? 'CHAR' : 'VARCHAR') . '(' . $args[0] . ')' : 'TEXT'); if (!$pg) $sqlType = 'VARCHAR(' . ($args[0] ?? 255) . ')'; break;
            case 'enum': case 'set':
                $kind = 'text';
                $len = max(array_map('strlen', $args ?: ['x']));
                $sqlType = 'VARCHAR(' . max(10, $len) . ')';
                if (!$hasDefault && $notNull && $args) { $hasDefault = true; $default = ['str', $args[0]]; }
                break;
            case 'tinytext': case 'text': case 'mediumtext': case 'longtext': case 'json': $kind = 'text'; $sqlType = 'TEXT'; break;
            case 'blob': case 'tinyblob': case 'mediumblob': case 'longblob': case 'binary': case 'varbinary': $kind = 'blob'; $sqlType = $pg ? 'BYTEA' : 'BLOB'; break;
            case 'bit': case 'bool': case 'boolean': $kind = 'int'; $sqlType = 'SMALLINT'; break;
            default: $sqlType = strtoupper($type);
        }
        if ($driver === Dialect::SQLITE && $kind === 'text') $sqlType .= ' COLLATE NOCASE';

        $sql = '"' . $name . '" ' . $sqlType;
        if ($auto) {
            return ['name' => $name, 'sql' => $sql . ' NOT NULL', 'auto' => true];
        }

        // Zero dates do not exist outside MySQL: such columns become nullable.
        if ($hasDefault && $default && $default[0] === 'str' && preg_match('/^0000-00-00/', $default[1])) { $hasDefault = false; $notNull = false; }
        if (in_array($kind, ['date', 'datetime'], true) && !$hasDefault) $notNull = false;

        // MySQL fills an omitted NOT NULL column with 0 or ''; do the same.
        if ($notNull && !$hasDefault) {
            $hasDefault = true;
            $default = in_array($kind, ['int', 'num'], true) ? ['num', '0'] : ($kind === 'blob' ? null : ['str', '']);
            if ($default === null) $hasDefault = false;
        }

        if ($hasDefault && $default) {
            $sql .= ' DEFAULT ' . match ($default[0]) {
                'str' => in_array($kind, ['int', 'num'], true) && is_numeric($default[1]) ? $default[1] : "'" . str_replace("'", "''", $default[1]) . "'",
                'num' => $default[1],
                'null' => 'NULL',
                'ts' => $driver === Dialect::PGSQL ? 'CURRENT_TIMESTAMP' : 'CURRENT_TIMESTAMP',
            };
        }
        if ($notNull) $sql .= ' NOT NULL';
        return ['name' => $name, 'sql' => $sql, 'auto' => false];
    }

    // ---- ALTER TABLE -------------------------------------------------------------------------------

    private static function alterTable(string $sql, string $driver): array
    {
        $t = Dialect::tokenize($sql);
        $n = count($t);
        $i = 0;
        $table = '';
        for (; $i < $n; $i++) {
            if ($t[$i][0] === 'ident' || ($t[$i][0] === 'word' && !in_array(strtoupper($t[$i][1]), ['ALTER', 'TABLE', 'IGNORE'], true))) { $table = $t[$i][1]; $i++; break; }
        }
        // split actions on top-level commas
        $actions = [[]]; $depth = 0;
        for (; $i < $n; $i++) {
            $tk = $t[$i];
            if ($tk[0] === 'sym' && $tk[1] === '(') $depth++;
            if ($tk[0] === 'sym' && $tk[1] === ')') $depth--;
            if ($tk[0] === 'sym' && $tk[1] === ',' && $depth === 0) { $actions[] = []; continue; }
            $actions[count($actions) - 1][] = $tk;
        }
        $out = [];
        foreach ($actions as $act) {
            $a = array_values(array_filter($act, fn($x) => $x[0] !== 'ws'));
            if (!$a) continue;
            $w = strtoupper($a[0][1]);
            if ($w === 'ADD') {
                $w1 = strtoupper($a[1][1] ?? '');
                if (in_array($w1, ['KEY', 'INDEX', 'UNIQUE', 'FULLTEXT'], true)) {
                    if ($w1 === 'FULLTEXT') continue;
                    $unique = $w1 === 'UNIQUE';
                    $name = '';
                    foreach (array_slice($a, 1) as $tk) { if ($tk[0] === 'sym') break; if (in_array(strtoupper($tk[1]), ['KEY', 'INDEX', 'UNIQUE'], true)) continue; $name = $tk[1]; }
                    $cols = self::keyColumns($a);
                    $name = $table . '_' . ($name !== '' ? $name : implode('_', $cols));
                    $out[] = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX IF NOT EXISTS "' . $name . '" ON "' . $table . '" (' . implode(', ', array_map(fn($c) => '"' . $c . '"', $cols)) . ')';
                    continue;
                }
                if ($w1 === 'PRIMARY') continue;
                $rest = array_slice($a, strtoupper($a[1][1] ?? '') === 'COLUMN' ? 2 : 1);
                // drop AFTER x / FIRST
                $clean = [];
                for ($k = 0; $k < count($rest); $k++) {
                    if (strtoupper($rest[$k][1]) === 'AFTER') { $k++; continue; }
                    if (strtoupper($rest[$k][1]) === 'FIRST') continue;
                    $clean[] = $rest[$k];
                }
                $col = self::column($clean, $driver);
                $sqlCol = $col['auto'] && $driver === Dialect::SQLITE ? '"' . $col['name'] . '" INTEGER' : $col['sql'];
                // SQLite cannot add a NOT NULL column without a default; ours always have one.
                $out[] = 'ALTER TABLE "' . $table . '" ADD COLUMN ' . $sqlCol;
            } elseif ($w === 'DROP') {
                $w1 = strtoupper($a[1][1] ?? '');
                if ($w1 === 'INDEX' || $w1 === 'KEY') $out[] = 'DROP INDEX IF EXISTS "' . $table . '_' . ($a[2][1] ?? '') . '"';
                elseif ($w1 === 'COLUMN') $out[] = 'ALTER TABLE "' . $table . '" DROP COLUMN "' . ($a[2][1] ?? '') . '"';
                else $out[] = 'ALTER TABLE "' . $table . '" DROP COLUMN "' . ($a[1][1] ?? '') . '"';
            } elseif (($w === 'MODIFY' || $w === 'CHANGE') && $driver === Dialect::PGSQL) {
                $rest = array_slice($a, strtoupper($a[1][1] ?? '') === 'COLUMN' ? 2 : 1);
                if ($w === 'CHANGE') { $old = $rest[0][1]; $rest = array_slice($rest, 1); if ($old !== $rest[0][1]) $out[] = 'ALTER TABLE "' . $table . '" RENAME COLUMN "' . $old . '" TO "' . $rest[0][1] . '"'; }
                $col = self::column($rest, $driver);
                if (preg_match('/^"[^"]+" (.+?)( DEFAULT .+?)?( NOT NULL)?$/', $col['sql'], $m)) {
                    $out[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $col['name'] . '" TYPE ' . $m[1] . ' USING "' . $col['name'] . '"::' . strtok($m[1], '(');
                }
            }
            // ENGINE=, CONVERT TO CHARACTER SET, AUTO_INCREMENT=, ORDER BY …: nothing to do.
        }
        return $out;
    }
}
