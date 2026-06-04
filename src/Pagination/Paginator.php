<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Pagination;

use Hibla\QueryBuilder\Interfaces\Pagination\PaginatorInterface;
use Rcalicdan\ConfigLoader\Config;

/**
 * Standard Offset-based Paginator.
 */
class Paginator extends AbstractPaginator implements PaginatorInterface
{
    /**
     * @param array<int|string, mixed> $items
     * @param int $total
     * @param int $perPage
     * @param int $currentPage
     * @param string|null $path
     * @param string|null $query
     */
    public function __construct(
        array $items,
        public private(set) int $total,
        int $perPage,
        public private(set) int $currentPage,
        ?string $path = null,
        private readonly ?string $query = null,
    ) {
        parent::__construct($items, $perPage, $path);
    }

    /**
     * @inheritDoc
     */
    public int $lastPage {
        get => (int) ceil($this->total / $this->perPage);
    }

    /**
     * @inheritDoc
     */
    public int $from {
        get {
            if ($this->total === 0) {
                return 0;
            }

            return ($this->currentPage - 1) * $this->perPage + 1;
        }
    }

    /**
     * @inheritDoc
     */
    public int $to {
        get {
            if ($this->total === 0) {
                return 0;
            }

            return min($this->currentPage * $this->perPage, $this->total);
        }
    }

    /**
     * @inheritDoc
     */
    public bool $hasMore {
        get => $this->currentPage < $this->lastPage;
    }

    /**
     * @inheritDoc
     */
    public bool $hasPages {
        get => $this->lastPage > 1;
    }

    /**
     * @inheritDoc
     */
    public bool $isFirstPage {
        get => $this->currentPage === 1;
    }

    /**
     * @inheritDoc
     */
    public bool $isLastPage {
        get => $this->currentPage >= $this->lastPage;
    }

    /**
     * {@inheritDoc}
     */
    public function nextPageUrl(?string $basePath = null): ?string
    {
        if (! $this->hasMore) {
            return null;
        }

        $originalPath = $this->path;
        if ($basePath !== null) {
            $this->path = $basePath;
        }

        $url = $this->url($this->currentPage + 1);
        $this->path = $originalPath;

        return $url;
    }

    /**
     * {@inheritDoc}
     */
    public function previousPageUrl(?string $basePath = null): ?string
    {
        if ($this->currentPage <= 1) {
            return null;
        }

        $originalPath = $this->path;
        if ($basePath !== null) {
            $this->path = $basePath;
        }

        $url = $this->url($this->currentPage - 1);
        $this->path = $originalPath;

        return $url;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function render(?string $template = null, ?string $basePath = null): string
    {
        if ($template === null) {
            /** @var string $template */
            $template = Config::loadFromRoot('pdo-schema.pagination.default_template') ?? 'tailwind';
        }

        if (! $this->hasPages) {
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
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'from' => $this->from,
                'to' => $this->to,
            ],
            'links' => [
                'first' => $this->path !== null ? $this->url(1) : null,
                'last' => $this->path !== null ? $this->url($this->lastPage) : null,
                'prev' => $this->previousPageUrl($basePath),
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
