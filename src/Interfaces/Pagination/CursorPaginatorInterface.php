<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces\Pagination;

/**
 * Cursor-based Paginator Contract.
 */
interface CursorPaginatorInterface extends BasePaginatorInterface
{
    /**
     * Get the encoded next cursor token, or null if no more pages remain.
     *
     * @var string|null
     */
    public ?string $nextCursor { get; }

    /**
     * Get the columns and directions used for cursor sorting.
     *
     * @var array<string, string>
     */
    public array $cursorColumns { get; }
}
