<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Exceptions\SchemaCompilerException;

/**
 * Handles default value compilation for different database systems
 */
class DefaultValueCompiler
{
     /**
     * A list of database expressions that should not be quoted.
     *
     * @var list<string>
     */
    protected array $expressionList = [];

    public function compile(mixed $default): string
    {
        if ($default === null) {
            return $this->formatNull();
        }

        if (\is_bool($default)) {
            return $this->formatBoolean($default);
        }

        if (is_numeric($default)) {
            return $this->formatNumeric(+$default);
        }

        if (\is_string($default)) {
            if ($this->isExpression($default)) {
                return $this->formatExpression($default);
            }

            return $this->formatString($default);
        }

        if (\is_object($default) && method_exists($default, '__toString')) {
            return $this->formatString((string) $default);
        }

        throw new SchemaCompilerException('Unsupported type for default value: ' . \gettype($default));
    }

    protected function isExpression(string $value): bool
    {
        return \in_array(strtoupper($value), $this->expressionList, true);
    }

    protected function formatNull(): string
    {
        return 'NULL';
    }

    protected function formatBoolean(bool $value): string
    {
        return $value ? '1' : '0';
    }

    protected function formatNumeric(int|float $value): string
    {
        return (string) $value;
    }

    protected function formatExpression(string $value): string
    {
        return $value;
    }

    protected function formatString(string $value): string
    {
        $escapedValue = str_replace("'", "''", $value);

        return "'{$escapedValue}'";
    }
}
