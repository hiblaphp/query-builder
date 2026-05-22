<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

/**
 * Helper class for cursor-based pagination operations.
 */
class CursorPaginationHelper
{
    /**
     * Decode and validate a cursor value.
     */
    public static function decodeCursor(?string $cursor): string|false
    {
        if (! \is_string($cursor) || $cursor === '') {
            return false;
        }

        return base64_decode($cursor, true);
    }

    /**
     * Encode a cursor value.
     */
    public static function encodeCursor(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! \is_scalar($value) && ! (\is_object($value) && method_exists($value, '__toString'))) {
            return null;
        }

        return base64_encode((string) $value);
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
     * Resolve the next cursor from results.
     *
     * @param array<mixed> $results
     */
    public static function resolveNextCursor(
        array $results,
        string $cursorColumn,
        bool $hasMore
    ): ?string {
        if (! $hasMore || \count($results) === 0) {
            return null;
        }

        /** @var array<mixed>|object $lastItem */
        $lastItem = end($results);
        $cursorValue = self::extractColumnValue($lastItem, $cursorColumn);

        return self::encodeCursor($cursorValue);
    }

    /**
     * Apply cursor condition to the query builder.
     *
     * @param Builder $builder
     * @param string|null $cursor
     * @param string $cursorColumn
     *
     * @return Builder
     */
    public static function applyCursor(
        Builder $builder,
        ?string $cursor,
        string $cursorColumn
    ): Builder {
        $cursorValue = self::decodeCursor($cursor);

        if ($cursorValue === false) {
            return $builder;
        }

        return $builder->where($cursorColumn, '>', $cursorValue);
    }
}
