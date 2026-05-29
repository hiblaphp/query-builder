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
     * Get the name of the column used for cursor sorting.
     */
    public function getCursorColumn(): string;
}
