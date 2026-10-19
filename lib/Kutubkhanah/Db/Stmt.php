<?php
/**
 * Kutubkhanah: prepared statement with the mysqli_stmt API on top of PDO.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

final class Stmt
{
    public int|string $insert_id = 0;
    public int|string $affected_rows = 0;
    public int $num_rows = 0;
    public int $param_count = 0;
    public int $field_count = 0;
    public int $errno = 0;
    public string $error = '';
    public string $sqlstate = '00000';

    private ?\PDOStatement $st = null;
    private array $types = [];
    private array $refs = [];
    private array $boundResult = [];
    private ?Result $result = null;

    public function __construct(private Mysqli $db, private Pdo $pdo, private string $sql)
    {
        $this->st = $pdo->prepare($sql);
        $this->param_count = substr_count(preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '', $sql), '?');
    }

    public function bind_param(string $types, mixed &...$vars): bool
    {
        $this->types = str_split($types);
        $this->refs = [];
        foreach ($vars as $i => &$v) $this->refs[$i] = &$v;
        return true;
    }

    public function bind_result(mixed &...$vars): bool
    {
        $this->boundResult = [];
        foreach ($vars as $i => &$v) $this->boundResult[$i] = &$v;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->errno = 0; $this->error = '';
        try {
            if ($params !== null) {
                $i = 1;
                foreach ($params as $v) $this->st->bindValue($i++, $v, $v === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            } else {
                foreach ($this->refs as $i => $v) {
                    $t = $this->types[$i] ?? 's';
                    $v = $v ?? null;
                    $pdoType = $v === null ? \PDO::PARAM_NULL : ($t === 'i' ? \PDO::PARAM_INT : ($t === 'b' ? \PDO::PARAM_LOB : \PDO::PARAM_STR));
                    $this->st->bindValue($i + 1, $t === 'd' && $v !== null ? (string)$v : $v, $pdoType);
                }
            }
            $this->st->execute();
        } catch (\PDOException $e) {
            [$this->errno, $this->sqlstate] = [Mysqli::errnoFor($e), (string)($e->errorInfo[0] ?? 'HY000')];
            $this->error = $e->getMessage();
            if (Mysqli::throws()) throw new \mysqli_sql_exception($e->getMessage(), $this->errno);
            return false;
        }
        $this->field_count = $this->st->columnCount();
        $this->result = null;
        if ($this->field_count > 0) {
            $this->result = Result::from($this->st);
            $this->num_rows = $this->result->num_rows;
        } else {
            $this->affected_rows = $this->st->rowCount();
            if (preg_match('/^\s*INSERT/i', $this->sql)) $this->insert_id = (int)$this->pdo->lastInsertId();
        }
        return true;
    }

    public function get_result(): Result|false
    {
        return $this->result ?? false;
    }

    public function store_result(): bool { return true; }

    public function fetch(): ?bool
    {
        if (!$this->result) return null;
        $row = $this->result->fetch_row();
        if ($row === null) return null;
        foreach ($this->boundResult as $i => &$ref) $ref = $row[$i] ?? null;
        return true;
    }

    public function data_seek(int $offset): void { $this->result?->data_seek($offset); }
    public function free_result(): void { $this->result?->free(); }
    public function reset(): bool { return true; }
    public function close(): bool { $this->st = null; return true; }
    public function result_metadata(): Result|false { return $this->result ?? false; }
    public function attr_get(int $attribute): int|false { return 0; }
    public function attr_set(int $attribute, int $value): bool { return true; }
}
