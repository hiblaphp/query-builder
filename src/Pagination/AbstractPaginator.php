<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Pagination;

use Hibla\QueryBuilder\Interfaces\Pagination\BasePaginatorInterface;

/**
 * Base implementation for all pagination classes.
 */
abstract class AbstractPaginator implements BasePaginatorInterface
{
    protected static ?TemplateEngine $templateEngine = null;

    /**
     * The query string variables that should be appended to links.
     *
     * @var array<string, mixed>
     */
    protected array $queryParameters = [];

    /**
     * The URL fragment to add to all URLs.
     *
     * @var string|null
     */
    protected ?string $urlFragment = null;

    /**
     * @param array<int|string, mixed> $items
     * @param int $perPage
     * @param string|null $path
     */
    public function __construct(
        public private(set) array $items,
        public private(set) int $perPage,
        public ?string $path = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function appends(array|string $key, mixed $value = null): static
    {
        $clone = clone $this;

        if (\is_array($key)) {
            $clone->queryParameters = [...$clone->queryParameters, ...$key];
        } else {
            $clone->queryParameters[$key] = $value;
        }

        return $clone;
    }

   /**
     * {@inheritDoc}
     */
    public function withQueryString(): static
    {
        /** @var array<string, mixed> $query */
        $query = [];

        foreach ($_GET as $key => $value) {
            $query[(string) $key] = $value;
        }

        unset($query['page'], $query['cursor']);

        return $this->appends($query);
    }

    /**
     * {@inheritDoc}
     */
    public function fragment(string $fragment): static
    {
        $clone = clone $this;

        $clone->urlFragment = ltrim($fragment, '#');

        return $clone;
    }

    /**
     * Build the formatted fragment string for the URL.
     *
     * @return string
     */
    protected function buildFragment(): string
    {
        return $this->urlFragment !== null ? '#' . $this->urlFragment : '';
    }

    /**
     * Set a custom templates directory path globally.
     *
     * @param string $path
     *
     * @return void
     */
    public static function setTemplatesPath(string $path): void
    {
        self::$templateEngine = new TemplateEngine($path);
    }

    /**
     * Get or instantiate the template engine.
     *
     * @return TemplateEngine
     */
    protected static function getTemplateEngine(): TemplateEngine
    {
        return self::$templateEngine ??= new TemplateEngine();
    }

    /**
     * {@inheritDoc}
     */
    public function links(?string $view = null, ?string $basePath = null): string
    {
        return $this->render($view, $basePath);
    }
}
