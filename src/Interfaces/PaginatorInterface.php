<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

interface PaginatorInterface
{
    /**
     * Get the active items for the current page.
     *
     * @return array<int|string, mixed>
     */
    public function items(): array;

    /**
     * Get the total number of records across all pages.
     */
    public function total(): int;

    /**
     * Get the number of items shown per page.
     */
    public function perPage(): int;

    /**
     * Get the current page number.
     */
    public function currentPage(): int;

    /**
     * Get the total number of pages.
     */
    public function lastPage(): int;

    /**
     * Get the starting record number for the current page.
     */
    public function from(): int;

    /**
     * Get the ending record number for the current page.
     */
    public function to(): int;

    /**
     * Check if there are more pages available after the current one.
     */
    public function hasMore(): bool;

    /**
     * Check if there are enough records to split across multiple pages.
     */
    public function hasPages(): bool;

    /**
     * Determine if the current page is the first page.
     */
    public function isFirstPage(): bool;

    /**
     * Determine if the current page is the last page.
     */
    public function isLastPage(): bool;

    /**
     * Get the URL for the next page, or null if on the last page.
     */
    public function nextPageUrl(): ?string;

    /**
     * Get the URL for the previous page, or null if on the first page.
     */
    public function previousPageUrl(): ?string;

    /**
     * Get the URL for a specific page number.
     */
    public function url(int $page): string;

    /**
     * Get a map of page numbers to their respective URLs for a given range.
     *
     * @return array<int, string>
     */
    public function getUrlRange(int $start, int $end): array;

    /**
     * Render the pagination HTML using a template.
     */
    public function render(?string $template = null): string;

    /**
     * Alias for render() to match popular view conventions.
     */
    public function links(?string $view = null): string;

    /**
     * Convert the paginator state and items to a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $includeItems = true): array;

    /**
     * Convert the paginator state and items to a JSON string.
     */
    public function toJson(bool $includeItems = true): string;

    /**
     * Send the paginator data directly as a JSON HTTP response.
     */
    public function respondJson(int $statusCode = 200, bool $includeItems = true): void;
}
