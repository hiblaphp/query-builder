<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces\Pagination;

/**
 * Standard Offset-based Paginator Contract.
 */
interface PaginatorInterface extends BasePaginatorInterface
{
    /**
     * Get the total number of records across all pages.
     *
     * @return int
     */
    public function total(): int;

    /**
     * Get the current page number.
     *
     * @return int
     */
    public function currentPage(): int;

    /**
     * Get the total number of pages.
     *
     * @return int
     */
    public function lastPage(): int;

    /**
     * Get the starting record number for the current page.
     *
     * @return int
     */
    public function from(): int;

    /**
     * Get the ending record number for the current page.
     *
     * @return int
     */
    public function to(): int;

    /**
     * Determine if there are enough records to split across multiple pages.
     *
     * @return bool
     */
    public function hasPages(): bool;

    /**
     * Determine if the current page is the first page.
     *
     * @return bool
     */
    public function isFirstPage(): bool;

    /**
     * Determine if the current page is the last page.
     *
     * @return bool
     */
    public function isLastPage(): bool;

    /**
     * Get the URL for the previous page, or null if on the first page.
     *
     * @param string|null $basePath Optional base path override.
     *
     * @return string|null
     */
    public function previousPageUrl(?string $basePath = null): ?string;

    /**
     * Get the URL for a specific page number.
     *
     * @param int $page The target page number.
     *
     * @return string
     */
    public function url(int $page): string;

    /**
     * Get a map of page numbers to their respective URLs for a given range.
     *
     * @param int $start Starting page number.
     * @param int $end Ending page number.
     *
     * @return array<int, string> Map of page numbers to URLs.
     */
    public function getUrlRange(int $start, int $end): array;
}
