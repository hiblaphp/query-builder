<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\ForeignKey;

/**
 * Handles foreign key compilation across database systems
 */
class ForeignKeyCompiler
{
    protected string $columnDelimiter = '`, `';

    protected string $openQuote = '`';

    protected string $closeQuote = '`';

    public function compile(ForeignKey $foreignKey): string
    {
        $cols = implode($this->columnDelimiter, $foreignKey->getColumns());
        $refCols = implode($this->columnDelimiter, $foreignKey->getReferenceColumns());

        $sql = "CONSTRAINT {$this->quoteName($foreignKey->getName())} FOREIGN KEY ({$this->openQuote}{$cols}{$this->closeQuote}) " .
            "REFERENCES {$this->quoteName($foreignKey->getReferenceTable())} ({$this->openQuote}{$refCols}{$this->closeQuote})";

        $sql = $this->appendOnDelete($sql, $foreignKey->getOnDelete());
        $sql = $this->appendOnUpdate($sql, $foreignKey->getOnUpdate());

        return $sql;
    }

    protected function quoteName(?string $name): ?string
    {
        if ($name === null) {
            return '';
        }

        return $this->openQuote . $name . $this->closeQuote;
    }

    protected function appendOnDelete(string $sql, ?string $action): string
    {
        if ($action === null || $action === 'RESTRICT') {
            return $sql;
        }

        return $sql . " ON DELETE {$action}";
    }

    protected function appendOnUpdate(string $sql, ?string $action): string
    {
        if ($action === null || $action === 'RESTRICT') {
            return $sql;
        }

        return $sql . " ON UPDATE {$action}";
    }
}
