<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

interface CursorPaginatorInterface
{
    /**
     * Get the active items for the current cursor window.
     *
     * @return array<int|string, mixed>
     */
    public function items(): array;

    /**
     * Get the number of items shown per window.
     */
    public function perPage(): int;

    /**
     * Get the encoded next cursor token, or null if no more pages remain.
     */
    public function nextCursor(): ?string;

    /**
     * Check if there are more items to paginate.
     */
    public function hasMore(): bool;

    /**
     * Get the URL for the next page, or null if on the last page.
     */
    public function nextPageUrl(?string $basePath = null): ?string;

    /**
     * Render the cursor pagination HTML using a template.
     */
    public function render(?string $template = null, ?string $basePath = null): string;

    /**
     * Alias for render() to match popular view conventions.
     */
    public function links(?string $view = null, ?string $basePath = null): string;

    /**
     * Convert the cursor paginator state and items to a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $includeItems = true, ?string $basePath = null): array;

    /**
     * Convert the cursor paginator state and items to a JSON string.
     */
    public function toJson(bool $includeItems = true, ?string $basePath = null): string;

    /**
     * Send the cursor paginator data directly as a JSON HTTP response.
     */
    public function respondJson(int $statusCode = 200, bool $includeItems = true, ?string $basePath = null): void;

    /**
     * Get the name of the column used for the cursor sorting.
     */
    public function getCursorColumn(): string;
}
