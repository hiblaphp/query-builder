<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces\Pagination;

/**
 * Shared interface for all pagination styles.
 *
 * @extends \IteratorAggregate<int|string, mixed>
 */
interface BasePaginatorInterface extends \IteratorAggregate
{
    /**
     * Get the active items for the current page/window.
     *
     * @return array<int|string, mixed>
     */
    public function items(): array;

    /**
     * Get the number of items shown per page/window.
     */
    public function perPage(): int;

    /**
     * Get the current base request path.
     *
     * @return string|null
     */
    public function path(): ?string;

    /**
     * Check if there are more items to paginate.
     */
    public function hasMore(): bool;

    /**
     * Get the URL for the next page/window.
     *
     * @param string|null $basePath Optional base path override.
     */
    public function nextPageUrl(?string $basePath = null): ?string;

    /**
     * Render the pagination HTML using a template.
     *
     * @param string|null $template Template name (e.g. 'bootstrap', 'tailwind').
     * @param string|null $basePath Optional base path override.
     *
     * @return string Rendered HTML template.
     */
    public function render(?string $template = null, ?string $basePath = null): string;

    /**
     * Alias for render() to match Laravel conventions.
     *
     * @param string|null $view Template name (e.g. 'bootstrap', 'tailwind').
     * @param string|null $basePath Optional base path override.
     *
     * @return string Rendered HTML template.
     */
    public function links(?string $view = null, ?string $basePath = null): string;

    /**
     * Convert the paginator state to a plain associative array.
     *
     * @param bool $includeItems Whether to include the actual dataset items.
     * @param string|null $basePath Optional base path override for generated URLs.
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $includeItems = true, ?string $basePath = null): array;

    /**
     * Convert the paginator state to a formatted JSON string.
     *
     * @param bool $includeItems Whether to include the actual dataset items.
     * @param string|null $basePath Optional base path override for generated URLs.
     */
    public function toJson(bool $includeItems = true, ?string $basePath = null): string;
}
