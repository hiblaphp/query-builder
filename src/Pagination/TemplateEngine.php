<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Pagination;

use Hibla\QueryBuilder\Exceptions\TemplateNotFoundException;

class TemplateEngine
{
    private string $templatesPath;

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
    }

    /**
     * Render a template with variables
     *
     * @param string $template Template name (supports dot notation: 'folder.template' or 'bootstrap')
     * @param array<string, mixed> $data Variables to pass to template
     *
     * @return string Rendered HTML
     *
     * @throws TemplateNotFoundException
     */
    public function render(string $template, array $data = []): string
    {
        if (str_contains($template, '::')) {
            $template = explode('::', $template)[1];
        }

        $templatePath = $this->getTemplatePath($template);

        if (! file_exists($templatePath)) {
            throw new TemplateNotFoundException("Template not found: {$template} at {$templatePath}");
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

        $path = $this->templatesPath . DIRECTORY_SEPARATOR . $templatePath . '.php';

        return $path;
    }

    /**
     * Check if template exists
     */
    public function exists(string $template): bool
    {
        if (str_contains($template, '::')) {
            $template = explode('::', $template)[1];
        }

        return file_exists($this->getTemplatePath($template));
    }
}
