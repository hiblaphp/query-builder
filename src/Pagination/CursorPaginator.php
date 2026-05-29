<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Pagination;

use Hibla\QueryBuilder\Interfaces\CursorPaginatorInterface;
use Rcalicdan\ConfigLoader\Config;

class CursorPaginator implements CursorPaginatorInterface
{
    private static ?TemplateEngine $templateEngine = null;

    /**
     * @param array<int|string, mixed> $items
     */
    public function __construct(
        private array $items,
        private int $perPage,
        private ?string $nextCursor,
        private string $cursorColumn,
        private ?string $path = null,
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
     * Get the current path
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Get items per page
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the cursor value for the next page
     */
    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * Check if there are more items to paginate
     */
    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * Get the next page URL
     */
    public function nextPageUrl(?string $basePath = null): ?string
    {
        if (! $this->hasMore()) {
            return null;
        }

        $basePath = $basePath ?? $this->path;

        if ($basePath === null) {
            return null;
        }

        $separator = str_contains($basePath, '?') ? '&' : '?';

        return $basePath . $separator . 'cursor=' . $this->nextCursor;
    }

    /**
     * Render cursor pagination using a template
     *
     * @param string $template Template name (cursor-simple, cursor-bootstrap, cursor-tailwind)
     * @param string|null $basePath Base path for pagination links. If null, uses current path
     */
    public function render(?string $template = null, ?string $basePath = null): string
    {
        if ($template === null) {
            /** @var string $template */
            $template = Config::loadFromRoot('pdo-schema.pagination.default_cursor_template') ?? 'cursor-simple';
        }

        if (! $this->hasMore()) {
            return '';
        }

        $engine = self::getTemplateEngine();

        if ($basePath !== null) {
            $this->path = $basePath;
        }

        return $engine->render($template, ['paginator' => $this]);
    }

    /**
     * Render cursor pagination links (alias for render, Laravel-style convenience method)
     *
     * @param string|null $view Template name (cursor-simple, cursor-bootstrap, cursor-tailwind). If null, uses 'cursor-simple'
     * @param string|null $basePath Base path for pagination links. If null, uses current path
     */
    public function links(?string $view = null, ?string $basePath = null): string
    {
        return $this->render($view ?? 'cursor-simple', $basePath);
    }

    /**
     * Return cursor pagination metadata as JSON
     *
     * @param bool $includeItems Include items in JSON response
     * @param string|null $basePath Base path for next page URL
     */
    public function toJson(bool $includeItems = true, ?string $basePath = null): string
    {
        $data = [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'per_page' => $this->perPage(),
                'has_more' => $this->hasMore(),
            ],
            'links' => [
                'next' => $this->nextPageUrl($basePath),
            ],
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '{}';
        }

        return $json;
    }

    /**
     * Return cursor pagination metadata as array
     *
     * @param bool $includeItems Include items in array response
     * @param string|null $basePath Base path for next page URL
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $includeItems = true, ?string $basePath = null): array
    {
        return [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'per_page' => $this->perPage(),
                'has_more' => $this->hasMore(),
            ],
            'links' => [
                'next' => $this->nextPageUrl($basePath),
            ],
        ];
    }

    /**
     * Get the cursor column name
     */
    public function getCursorColumn(): string
    {
        return $this->cursorColumn;
    }
}
