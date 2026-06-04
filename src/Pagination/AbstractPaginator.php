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
