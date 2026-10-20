<?php
/**
 * Kutubkhanah: PDOStatement that remembers its bound values for FOUND_ROWS() emulation.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Db;

final class Statement extends \PDOStatement
{
    private ?Pdo $owner = null;

    protected function __construct(?Pdo $owner = null)
    {
        $this->owner = $owner;
    }

    public function execute(?array $params = null): bool
    {
        $ok = $params === null ? parent::execute() : parent::execute($params);
        $this->owner?->noteExecuted($this->queryString, $params ?? []);
        return $ok;
    }
}
