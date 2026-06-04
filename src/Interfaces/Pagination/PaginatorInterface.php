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
     * @var int
     */
    public int $total { get; }

    /**
     * Get the current page number.
     *
     * @var int
     */
    public int $currentPage { get; }

    /**
     * Get the total number of pages.
     *
     * @var int
     */
    public int $lastPage { get; }

    /**
     * Get the starting record number for the current page.
     *
     * @var int
     */
    public int $from { get; }

    /**
     * Get the ending record number for the current page.
     *
     * @var int
     */
    public int $to { get; }

    /**
     * Determine if there are enough records to split across multiple pages.
     *
     * @var bool
     */
    public bool $hasPages { get; }

    /**
     * Determine if the current page is the first page.
     *
     * @var bool
     */
    public bool $isFirstPage { get; }

    /**
     * Determine if the current page is the last page.
     *
     * @var bool
     */
    public bool $isLastPage { get; }

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
