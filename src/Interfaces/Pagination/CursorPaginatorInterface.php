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
     */
    public function nextCursor(): ?string;

    /**
     * Get the columns and directions used for cursor sorting.
     *
     * @return array<string, string>
     */
    public function getCursorColumns(): array;
}
