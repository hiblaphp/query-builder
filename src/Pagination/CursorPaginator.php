<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Pagination;

use Hibla\QueryBuilder\Interfaces\Pagination\CursorPaginatorInterface;
use Rcalicdan\ConfigLoader\Config;

/**
 * Cursor-based Paginator.
 */
class CursorPaginator extends AbstractPaginator implements CursorPaginatorInterface
{
    /**
     * @param array<int|string, mixed> $items
     * @param int $perPage
     * @param string|null $nextCursor
     * @param string|array<int|string, string> $cursorColumns
     * @param string|null $path
     */
    public function __construct(
        array $items,
        int $perPage,
        private readonly ?string $nextCursor,
        private readonly string|array $cursorColumns,
        ?string $path = null,
    ) {
        parent::__construct($items, $perPage, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    public function getCursorColumns(): array
    {
        return \Hibla\QueryBuilder\Utilities\CursorPaginationHelper::normalizeColumns($this->cursorColumns);
    }

    /**
     * {@inheritDoc}
     */
    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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

        if ($basePath !== null) {
            $this->path = $basePath;
        }

        return self::getTemplateEngine()->render($template, ['paginator' => $this]);
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function toJson(bool $includeItems = true, ?string $basePath = null): string
    {
        $json = json_encode($this->toArray($includeItems, $basePath), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $json !== false ? $json : '{}';
    }

    /**
     * {@inheritDoc}
     *
     * @return \Generator<int|string, mixed, mixed, void>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->items as $key => $row) {
            yield $key => $row;
        }
    }
}
