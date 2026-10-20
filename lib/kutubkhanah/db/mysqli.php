<?php
/**
 * Kutubkhanah: the subset of mysqli that the base system code uses, on top of PDO for SQLite and PostgreSQL.
 * Errors throw mysqli_sql_exception, as mysqli does by default in PHP 8.1+.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

class Mysqli
{
    public int|string $insert_id = 0;
    public int|string $affected_rows = 0;
    public int $errno = 0;
    public string $error = '';
    public array $error_list = [];
    public string $sqlstate = '00000';
    public int $connect_errno = 0;
    public ?string $connect_error = null;
    public int $field_count = 0;
    public string $server_info = '';
    public string $host_info = 'localhost';
    public int $thread_id = 0;
    public string $info = '';
    public string $client_info = 'kutubkhanah-portable';
    public int $client_version = 80000;
    public int $server_version = 80000;
    public string $protocol_version = '10';
    public int $warning_count = 0;

    private bool $inTx = false;
    /** @var Result[] */
    private array $queue = [];

    public function __construct(private Pdo $pdo)
    {
        $this->server_info = $pdo->driverName();
    }

    public function pdo(): Pdo { return $this->pdo; }
    public function driverName(): string { return $this->pdo->driverName(); }

    public static function throws(): bool { return true; }

    /** MySQL error numbers the application tests for. */
    public static function errnoFor(\PDOException $e): int
    {
        $state = (string)($e->errorInfo[0] ?? $e->getCode());
        $msg = $e->getMessage();
        if ($state === '23505' || stripos($msg, 'UNIQUE constraint failed') !== false) return 1062;
        if ($state === '23502' || stripos($msg, 'NOT NULL constraint failed') !== false) return 1048;
        if ($state === '42P01' || stripos($msg, 'no such table') !== false) return 1146;
        if ($state === '42703' || stripos($msg, 'no such column') !== false) return 1054;
        if ($state === '42601' || stripos($msg, 'syntax error') !== false) return 1064;
        return 1105;
    }

    private function fail(\PDOException $e, string $sql = ''): never
    {
        $this->errno = self::errnoFor($e);
        $this->error = $e->getMessage();
        $this->sqlstate = (string)($e->errorInfo[0] ?? 'HY000');
        $this->error_list = [['errno' => $this->errno, 'sqlstate' => $this->sqlstate, 'error' => $this->error]];
        $dev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
        throw new \mysqli_sql_exception($this->error . ($dev && $sql !== '' ? ' [SQL: ' . substr($sql, 0, 600) . ']' : ''), $this->errno);
    }

    public function query(string $query, int $result_mode = 0): Result|bool
    {
        $this->errno = 0; $this->error = ''; $this->error_list = [];
        try {
            $st = $this->pdo->query($query);
        } catch (\PDOException $e) {
            $this->fail($e, $query);
        }
        $this->field_count = $st->columnCount();
        if ($this->field_count > 0) return Result::from($st);
        $this->affected_rows = $st->rowCount();
        if (preg_match('/^\s*(INSERT|REPLACE)/i', $query)) $this->insert_id = (int)$this->pdo->lastInsertId();
        return true;
    }

    public function real_query(string $query): bool
    {
        $r = $this->query($query);
        $this->queue = $r instanceof Result ? [$r] : [];
        return true;
    }

    public function store_result(int $mode = 0): Result|false { return array_shift($this->queue) ?? false; }
    public function use_result(): Result|false { return array_shift($this->queue) ?? false; }

    /** Runs every statement now; results are kept for next_result(). */
    public function multi_query(string $query): bool
    {
        $this->queue = [];
        foreach (self::splitStatements($query) as $stmt) {
            $r = $this->query($stmt);
            $this->queue[] = $r instanceof Result ? $r : new Result();
        }
        return true;
    }

    public function more_results(): bool { return count($this->queue) > 1; }
    public function next_result(): bool { array_shift($this->queue); return (bool)$this->queue; }

    /** Split on semicolons that are outside quotes. */
    public static function splitStatements(string $sql): array
    {
        $out = []; $cur = ''; $q = null; $n = strlen($sql);
        for ($i = 0; $i < $n; $i++) {
            $c = $sql[$i];
            if ($q !== null) {
                $cur .= $c;
                if ($c === '\\' && $i + 1 < $n) { $cur .= $sql[++$i]; continue; }
                if ($c === $q) $q = null;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $cur .= $c; continue; }
            if ($c === ';') { if (trim($cur) !== '') $out[] = trim($cur); $cur = ''; continue; }
            $cur .= $c;
        }
        if (trim($cur) !== '') $out[] = trim($cur);
        return $out;
    }

    public function prepare(string $query): Stmt|false
    {
        try {
            return new Stmt($this, $this->pdo, $query);
        } catch (\PDOException $e) {
            $this->fail($e, $query);
        }
    }

    public function stmt_init(): Stmt|false { return false; }

    /** Same escaping as mysqli, so the dialect translator reads our own output correctly. */
    public function real_escape_string(string $string): string
    {
        return strtr($string, ["\0" => '\\0', "\n" => '\\n', "\r" => '\\r', '\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\x1a" => '\\Z']);
    }

    public function escape_string(string $string): string { return $this->real_escape_string($string); }

    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        if (!$this->pdo->inTransaction()) $this->pdo->beginTransaction();
        return true;
    }

    public function commit(int $flags = 0, ?string $name = null): bool
    {
        if ($this->pdo->inTransaction()) $this->pdo->commit();
        return true;
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        return true;
    }

    public function autocommit(bool $enable): bool
    {
        if (!$enable) $this->begin_transaction(); else $this->commit();
        return true;
    }

    public function select_db(string $database): bool { return true; }
    public function set_charset(string $charset): bool { return true; }
    public function character_set_name(): string { return 'utf8mb4'; }
    public function get_charset(): object { return (object)['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']; }
    public function ping(): bool { return true; }
    public function close(): bool { return true; }
    public function kill(int $process_id): bool { return true; }
    public function stat(): string|false { return 'Uptime: 0'; }
    public function get_server_info(): string { return $this->server_info; }
    public function dump_debug_info(): bool { return true; }
    public function options(int $option, string|int $value): bool { return true; }
    public function refresh(int $flags): bool { return true; }
    public function thread_safe(): bool { return true; }
}
