<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

/**
 * @internal class for converting numeric string values to proper PHP types.
 */
final class NumericConverter
{
    /**
     * Convert numeric string values to int or float in a result set.
     *
     * @param array<int, array<string, mixed>> $results
     *
     * @return array<int, array<string, mixed>>
     */
    public static function convertResultSet(array $results): array
    {
        if ($results === []) {
            return $results;
        }

        $firstRow = reset($results);
        if ($firstRow === false || ! \is_array($firstRow)) {
            return $results;
        }

        $columnKeys = array_keys($firstRow);

        foreach ($results as &$row) {
            self::convertRow($row, $columnKeys);
        }

        return $results;
    }

    /**
     * Convert numeric string values in a single row.
     * Time Complexity: O(m) where m is the number of columns in the row.
     *
     * @param array<string, mixed> $row
     * @param array<int, string>|null $columnKeys Optional pre-computed column keys for performance
     *
     * @return array<string, mixed>
     */
    public static function convertRowArray(array $row, ?array $columnKeys = null): array
    {
        $columnKeys ??= array_keys($row);
        self::convertRow($row, $columnKeys);

        return $row;
    }

    /**
     * Convert a single value if it's a numeric string.
     * Time Complexity: O(1)
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function convertValue(mixed $value): mixed
    {
        if (\is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        return $value;
    }

    /**
     * Internal method to convert row by reference using pre-computed keys.
     * Time Complexity: O(m) where m is the number of columns.
     *
     * @param array<string, mixed> &$row
     * @param array<int, string> $columnKeys
     *
     * @return void
     */
    private static function convertRow(array &$row, array $columnKeys): void
    {
        foreach ($columnKeys as $key) {
            if (isset($row[$key]) && \is_string($row[$key]) && is_numeric($row[$key])) {
                $row[$key] += 0;
            }
        }
    }
}
