<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Pagination;

use Hibla\QueryBuilder\Exceptions\TemplateNotFoundException;

class TemplateEngine
{
    private string $templatesPath;

    /**
     * In-memory cache to prevent blocking file I/O.
     *
     * @var array<string, string>
     */
    private static array $pathCache = [];

    /**
     * Maximum number of cached paths to prevent memory leaks in long-running processes.
     */
    private const int MAX_CACHE_SIZE = 50;

    public function __construct(?string $templatesPath = null)
    {
        $this->templatesPath = $templatesPath ?? __DIR__ . DIRECTORY_SEPARATOR . 'templates';
    }

    /**
     * Register a custom templates path
     */
    public static function setTemplatesPath(string $path): void
    {
        if (! is_dir($path)) {
            throw new TemplateNotFoundException("Templates path does not exist: {$path}");
        }

        self::$pathCache = [];
    }

    /**
     * Render a template with optional data
     * 
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        if (str_contains($template, '::')) {
            $template = explode('::', $template)[1];
        }

        if (isset(self::$pathCache[$template])) {
            $templatePath = self::$pathCache[$template];
        } else {
            if (\count(self::$pathCache) >= self::MAX_CACHE_SIZE) {
                self::$pathCache = [];
            }

            $templatePath = $this->getTemplatePath($template);

            if (! file_exists($templatePath)) {
                throw new TemplateNotFoundException("Template not found: {$template} at {$templatePath}");
            }

            self::$pathCache[$template] = $templatePath;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $templatePath;

        $content = ob_get_clean();

        return $content !== false ? $content : '';
    }

    /**
     * Get full path to template file
     * Supports dot notation for nested directories (e.g., 'custom.bootstrap' -> 'custom/bootstrap.php')
     */
    private function getTemplatePath(string $template): string
    {
        $templatePath = str_replace('.', DIRECTORY_SEPARATOR, $template);

        return $this->templatesPath . DIRECTORY_SEPARATOR . $templatePath . '.php';
    }

    /**
     * Check if template exists
     */
    public function exists(string $template): bool
    {
        if (str_contains($template, '::')) {
            $template = explode('::', $template)[1];
        }

        if (isset(self::$pathCache[$template])) {
            return true;
        }

        return file_exists($this->getTemplatePath($template));
    }
}
