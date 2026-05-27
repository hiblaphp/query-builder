<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Pagination;

use Hibla\QueryBuilder\Interfaces\PaginatorInterface;
use Rcalicdan\ConfigLoader\Config;

class Paginator implements PaginatorInterface
{
    private static ?TemplateEngine $templateEngine = null;

    /**
     * @param array<int|string, mixed> $items
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $perPage,
        private int $currentPage,
        private ?string $path = null,
        private ?string $query = null,
    ) {
    }

    /**
     * Set a custom templates path
     */
    public static function setTemplatesPath(string $path): void
    {
        self::$templateEngine = new TemplateEngine($path);
    }

    /**
     * Get the template engine instance
     */
    private static function getTemplateEngine(): TemplateEngine
    {
        if (self::$templateEngine === null) {
            self::$templateEngine = new TemplateEngine();
        }

        return self::$templateEngine;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Get total items count
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get items per page
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the current page number
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the last page number
     */
    public function lastPage(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    /**
     * Get the starting item number for the current page
     */
    public function from(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * Get the end item number for the current page
     */
    public function to(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return min($this->currentPage * $this->perPage, $this->total);
    }

    /**
     * Check if there are more items to paginate
     */
    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Check if there are multiple pages
     */
    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    /**
     * Check if it's the first page
     */
    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Check if it's the last page
     */
    public function isLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage();
    }

    /**
     * Get the URL for the next page
     */
    public function nextPageUrl(): ?string
    {
        if (! $this->hasMore()) {
            return null;
        }

        return $this->url($this->currentPage + 1);
    }

    /**
     * Get the URL for the previous page
     */
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage <= 1) {
            return null;
        }

        return $this->url($this->currentPage - 1);
    }

    /**
     * Get the URL for a specific page
     */
    public function url(int $page): string
    {
        if ($this->path === null) {
            return '';
        }

        $separator = str_contains($this->path, '?') ? '&' : '?';
        $query = $this->query !== null ? $this->query . '&' : '';

        return $this->path . $separator . $query . 'page=' . $page;
    }

    /**
     * @return array<int, string>
     */
    public function getUrlRange(int $start, int $end): array
    {
        $urls = [];
        for ($page = $start; $page <= $end; $page++) {
            $urls[$page] = $this->url($page);
        }

        return $urls;
    }

    /**
     * Render pagination using a template
     *
     * @param string $template Template name (bootstrap, tailwind, simple)
     *
     * @return string Rendered HTML
     */
    public function render(?string $template = null): string
    {
        if ($template === null) {
            /** @var string $template */
            $template = Config::loadFromRoot('pdo-schema.pagination.default_template') ?? 'tailwind';
        }

        if (! $this->hasPages()) {
            return '';
        }

        $engine = self::getTemplateEngine();

        return $engine->render($template, ['paginator' => $this]);
    }

    /**
     * Render pagination links (alias for render, Laravel-style convenience method)
     *
     * @param string|null $view Template name (bootstrap, tailwind, simple). If null, uses 'bootstrap'
     */
    public function links(?string $view = null): string
    {
        return $this->render($view);
    }

    /**
     * Return pagination metadata as JSON
     *
     * @param bool $includeItems Include items in JSON response
     */
    public function toJson(bool $includeItems = true): string
    {
        $data = [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->from(),
                'to' => $this->to(),
            ],
            'links' => [
                'first' => $this->path !== null ? $this->url(1) : null,
                'last' => $this->path !== null ? $this->url($this->lastPage()) : null,
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '{}';
        }

        return $json;
    }

    /**
     * Return pagination metadata as array
     *
     * @param bool $includeItems Include items in array response
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $includeItems = true): array
    {
        return [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->from(),
                'to' => $this->to(),
            ],
            'links' => [
                'first' => $this->path !== null ? $this->url(1) : null,
                'last' => $this->path !== null ? $this->url($this->lastPage()) : null,
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }

    /**
     * Return pagination as JSON response
     * Useful for API responses
     *
     * @param int $statusCode HTTP status code
     * @param bool $includeItems Include items in response
     *
     * @return void
     */
    public function respondJson(int $statusCode = 200, bool $includeItems = true): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo $this->toJson($includeItems);
        exit;
    }
}
