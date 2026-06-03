<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;

/**
 * @internal class for cursor-based pagination operations.
 */
final class CursorPaginationHelper
{
    /**
     * Decode and validate a cursor value.
     *
     * @return array<string, mixed>|false
     */
    public static function decodeCursor(?string $cursor): array|false
    {
        if (! \is_string($cursor) || $cursor === '') {
            return false;
        }

        $decodedString = base64_decode($cursor, true);
        if ($decodedString === false) {
            return false;
        }

        $decodedJson = json_decode($decodedString, true);
        if (! \is_array($decodedJson)) {
            return false;
        }

        /** @var array<string, mixed> $decodedJson */
        return $decodedJson;
    }

    /**
     * Encode an array of cursor values.
     *
     * @param array<string, mixed> $values
     */
    public static function encodeCursor(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $json = json_encode($values);
        if ($json === false) {
            return null;
        }

        return base64_encode($json);
    }

    /**
     * Extract a column value from an array or object.
     *
     * @param array<mixed>|object $item
     */
    public static function extractColumnValue(array|object $item, string $column): mixed
    {
        if (\is_array($item)) {
            return $item[$column] ?? null;
        }

        $vars = get_object_vars($item);

        return $vars[$column] ?? null;
    }

    /**
     * Normalize cursor columns into [column => direction] format.
     *
     * @param string|array<int|string, string> $columns
     *
     * @return array<string, string>
     */
    public static function normalizeColumns(string|array $columns): array
    {
        if (\is_string($columns)) {
            return [$columns => 'asc'];
        }

        $normalized = [];
        foreach ($columns as $key => $value) {
            if (\is_int($key)) {
                $normalized[$value] = 'asc';
            } else {
                $normalized[$key] = strtolower($value) === 'desc' ? 'desc' : 'asc';
            }
        }

        return $normalized;
    }

    /**
     * Resolve the next cursor from results.
     *
     * @param array<mixed> $results
     * @param string|array<int|string, string> $cursorColumns
     */
    public static function resolveNextCursor(
        array $results,
        string|array $cursorColumns,
        bool $hasMore
    ): ?string {
        if (! $hasMore || \count($results) === 0) {
            return null;
        }

        $columns = self::normalizeColumns($cursorColumns);

        /** @var array<mixed>|object $lastItem */
        $lastItem = end($results);

        $cursorValues = [];
        foreach (array_keys($columns) as $col) {
            $cursorValues[$col] = self::extractColumnValue($lastItem, $col);
        }

        return self::encodeCursor($cursorValues);
    }

    /**
     * Apply cursor condition to the query builder.
     *
     * @param QueryBuilderInterface $builder
     * @param string|null $cursor
     * @param string|array<int|string, string> $cursorColumns
     *
     * @return QueryBuilderInterface
     */
    public static function applyCursor(
        QueryBuilderInterface $builder,
        ?string $cursor,
        string|array $cursorColumns
    ): QueryBuilderInterface {
        $cursorValues = self::decodeCursor($cursor);

        if ($cursorValues === false) {
            return $builder;
        }

        $columns = self::normalizeColumns($cursorColumns);
        $colNames = array_keys($columns);

        return $builder->whereGroup(function ($q) use ($columns, $cursorValues, $colNames) {
            for ($i = 0; $i < \count($colNames); $i++) {
                $q = $q->orWhereNested(function ($inner) use ($columns, $cursorValues, $colNames, $i) {

                    for ($j = 0; $j < $i; $j++) {
                        $prevCol = $colNames[$j];
                        $inner = $inner->where($prevCol, '=', $cursorValues[$prevCol] ?? null);
                    }

                    $currCol = $colNames[$i];
                    $direction = $columns[$currCol];

                    $operator = $direction === 'desc' ? '<' : '>';

                    $inner = $inner->where($currCol, $operator, $cursorValues[$currCol] ?? null);

                    return $inner;
                });
            }

            return $q;
        });
    }
}
