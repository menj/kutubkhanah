<?php
/**
 * Kutubkhanah: SQL backup for SQLite and PostgreSQL (the MySQL dump library only speaks MySQL).
 * Writes standard INSERT statements for every table. SQLite dumps also carry the CREATE statements;
 * PostgreSQL dumps hold the data only, to be loaded into a fresh Kutubkhanah install of the same version.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

class Dump
{
    public function __construct(private \PDO $pdo, private string $driver) {}

    private function tables(): array
    {
        if ($this->driver === 'sqlite') {
            return $this->raw("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
        }
        return $this->raw("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' ORDER BY table_name")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Run SQL untouched: the dump reads the native catalogue, so the MySQL translator must stay out of it. */
    private function raw(string $sql): \PDOStatement
    {
        return (new \ReflectionMethod(\PDO::class, 'query'))->invoke($this->pdo, $sql);
    }

    private function q(string $id): string
    {
        return '"' . str_replace('"', '""', $id) . '"';
    }

    private function literal(mixed $v): string
    {
        if ($v === null) return 'NULL';
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_int($v) || is_float($v)) return (string)$v;
        if (is_resource($v)) $v = stream_get_contents($v);
        return $this->pdo->quote((string)$v);
    }

    /** @throws \RuntimeException */
    public function start(string $file): void
    {
        $fh = @fopen($file, 'wb');
        if (!$fh) throw new \RuntimeException('Cannot write ' . $file);
        fwrite($fh, "-- Kutubkhanah backup (" . $this->driver . ") " . date('Y-m-d H:i:s') . "\n"
            . ($this->driver === 'pgsql' ? "-- Data only: load into a fresh install of the same version.\n" : '') . "\n");
        try {
            if ($this->driver === 'sqlite') {
                foreach ($this->raw("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END, name")->fetchAll(\PDO::FETCH_COLUMN) as $sql) {
                    fwrite($fh, $sql . ";\n");
                }
                fwrite($fh, "\n");
            }
            foreach ($this->tables() as $t) {
                $st = $this->raw('SELECT * FROM ' . $this->q($t));
                $cols = null; $batch = [];
                $flush = function () use (&$batch, &$cols, $fh, $t) {
                    if (!$batch) return;
                    fwrite($fh, 'INSERT INTO ' . $this->q($t) . ' (' . implode(', ', array_map([$this, 'q'], $cols)) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                };
                while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                    $cols ??= array_keys($row);
                    $batch[] = '(' . implode(', ', array_map([$this, 'literal'], array_values($row))) . ')';
                    if (count($batch) >= 200) $flush();
                }
                $flush();
            }
        } finally {
            fclose($fh);
        }
    }
}
