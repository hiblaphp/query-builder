<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\Column;

class PostgreSQLDefaultValueCompiler extends DefaultValueCompiler
{
    public function __construct()
    {
        $this->expressionList = [];
    }

    public function compile(mixed $default, ?Column $column = null): string
    {
        if ($default === null) {
            return 'NULL';
        }

        if (\is_bool($default)) {
            if ($column !== null && $column->getType() === 'TINYINT' && $column->getLength() === 1) {
                return $default ? 'true' : 'false';
            }

            return $default ? 'true' : 'false';
        }

        if (is_numeric($default)) {
            if ($column !== null && $column->getType() === 'TINYINT' && $column->getLength() === 1) {
                return ($default !== 0 && $default !== 0.0 && $default !== '0') ? 'true' : 'false';
            }

            return (string) $default;
        }

        if (\is_string($default)) {
            return "'".addslashes($default)."'";
        }

        return "''";
    }
}
