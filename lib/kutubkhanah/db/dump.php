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

    /** Run one statement untouched (see raw()). */
    private function rawExec(string $sql): void
    {
        (new \ReflectionMethod(\PDO::class, 'exec'))->invoke($this->pdo, $sql);
    }

    /** Read a backup file one complete statement at a time (quotes may span lines). */
    private function statements(string $file): \Generator
    {
        $fh = fopen($file, 'rb');
        if (!$fh) throw new \RuntimeException('Cannot read the file.');
        try {
            $buf = '';
            while (($line = fgets($fh)) !== false) {
                if ($buf === '' && (trim($line) === '' || str_starts_with($line, '--'))) continue;
                $buf .= $line;
                if (substr_count($buf, "'") % 2 === 0 && rtrim($buf) !== '' && str_ends_with(rtrim($buf), ';')) {
                    yield rtrim($buf);
                    $buf = '';
                }
            }
            if (trim($buf) !== '') yield trim($buf);
        } finally {
            fclose($fh);
        }
    }

    /**
     * Replace the catalogue with the contents of a backup written by start(). Only INSERT statements for tables that exist
     * are run; everything else in the file is ignored. Tables named in the file are emptied first, all in one transaction.
     * A copy of the current data is written to $safety before anything changes.
     *
     * @return array<string,int> rows restored per table
     * @throws \RuntimeException
     */
    public function restore(string $file, string $safety): array
    {
        $fh = fopen($file, 'rb');
        $head = $fh ? (string)fgets($fh) : '';
        if ($fh) fclose($fh);
        if (!preg_match('/^-- Kutubkhanah backup \((sqlite|pgsql)\)/', $head, $m)) {
            throw new \RuntimeException('This is not a Kutubkhanah SQLite or PostgreSQL backup.');
        }
        if ($m[1] !== $this->driver) {
            throw new \RuntimeException('This backup is for ' . $m[1] . ', but this site runs on ' . $this->driver . '.');
        }
        $known = array_flip($this->tables());
        $plan = [];
        foreach ($this->statements($file) as $sql) {
            if (!preg_match('/^INSERT INTO "((?:[^"]|"")+)" \(/', $sql, $t)) continue;
            $name = str_replace('""', '"', $t[1]);
            if (!isset($known[$name])) continue;
            $plan[$name] = ($plan[$name] ?? 0) + 1;
        }
        if (!$plan) throw new \RuntimeException('The backup holds no rows to restore.');

        $this->start($safety);

        $counts = [];
        $this->pdo->beginTransaction();
        try {
            foreach (array_keys($plan) as $name) $this->rawExec('DELETE FROM ' . $this->q($name));
            foreach ($this->statements($file) as $sql) {
                if (!preg_match('/^INSERT INTO "((?:[^"]|"")+)" \(/', $sql, $t)) continue;
                $name = str_replace('""', '"', $t[1]);
                if (!isset($plan[$name])) continue;
                $this->rawExec($sql);
                $counts[$name] = ($counts[$name] ?? 0) + substr_count($sql, "\n(") + 1;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new \RuntimeException('Restore failed and nothing was changed: ' . $e->getMessage());
        }
        if ($this->pdo instanceof Pdo) $this->pdo->resetSequences();
        return $counts;
    }
}
