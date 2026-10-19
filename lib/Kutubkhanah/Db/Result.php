<?php
/**
 * Kutubkhanah: a buffered result set with the mysqli_result API.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

final class Result implements \IteratorAggregate, \Countable
{
    public int $num_rows = 0;
    public int $field_count = 0;
    public int $current_field = 0;
    /** @var string[] */
    private array $names = [];
    /** @var array<int,array<int,mixed>> */
    private array $rows = [];
    private int $pos = 0;

    public static function from(\PDOStatement $st): self
    {
        $r = new self();
        $n = $st->columnCount();
        for ($i = 0; $i < $n; $i++) {
            $meta = $st->getColumnMeta($i);
            $r->names[] = (string)($meta['name'] ?? $i);
        }
        $r->rows = $st->fetchAll(\PDO::FETCH_NUM);
        $r->num_rows = count($r->rows);
        $r->field_count = $n;
        return $r;
    }

    private function assoc(array $row): array
    {
        $a = [];
        foreach ($this->names as $i => $name) $a[$name] = $row[$i] ?? null;
        return $a;
    }

    public function fetch_assoc(): array|null|false
    {
        if ($this->pos >= $this->num_rows) return null;
        return $this->assoc($this->rows[$this->pos++]);
    }

    public function fetch_row(): array|null|false
    {
        if ($this->pos >= $this->num_rows) return null;
        return $this->rows[$this->pos++];
    }

    public function fetch_array(int $mode = MYSQLI_BOTH): array|null|false
    {
        if ($this->pos >= $this->num_rows) return null;
        $row = $this->rows[$this->pos++];
        if ($mode === MYSQLI_NUM) return $row;
        $a = $this->assoc($row);
        return $mode === MYSQLI_ASSOC ? $a : $a + $row;
    }

    public function fetch_object(string $class = 'stdClass', array $constructor_args = []): object|null|false
    {
        $a = $this->fetch_assoc();
        if (!$a) return $a;
        $o = $class === 'stdClass' ? new \stdClass() : new $class(...$constructor_args);
        foreach ($a as $k => $v) $o->$k = $v;
        return $o;
    }

    public function fetch_all(int $mode = MYSQLI_NUM): array
    {
        $out = [];
        while (($r = $this->fetch_array($mode)) !== null) $out[] = $r;
        return $out;
    }

    public function fetch_column(int $column = 0): string|int|float|null|false
    {
        $r = $this->fetch_row();
        return $r === null ? false : ($r[$column] ?? null);
    }

    public function fetch_field(): object|false
    {
        if ($this->current_field >= $this->field_count) return false;
        return $this->fetch_field_direct($this->current_field++);
    }

    public function fetch_fields(): array
    {
        return array_map(fn($i) => $this->fetch_field_direct($i), array_keys($this->names));
    }

    public function fetch_field_direct(int $index): object|false
    {
        if (!isset($this->names[$index])) return false;
        $o = new \stdClass();
        $o->name = $o->orgname = $this->names[$index];
        $o->table = $o->orgtable = '';
        $o->def = '';
        $o->db = '';
        $o->length = $o->max_length = 0;
        $o->flags = 0;
        $o->type = 253;
        $o->decimals = 0;
        return $o;
    }

    public function field_seek(int $index): bool { $this->current_field = $index; return true; }

    public function data_seek(int $offset): bool
    {
        if ($offset < 0 || $offset > $this->num_rows) return false;
        $this->pos = $offset;
        return true;
    }

    public function free(): void { $this->rows = []; }
    public function close(): void { $this->rows = []; }
    public function free_result(): void { $this->rows = []; }

    public function getIterator(): \Iterator
    {
        $this->pos = 0;
        return (function () { while (($r = $this->fetch_assoc()) !== null) yield $r; })();
    }

    public function count(): int { return $this->num_rows; }
}
