<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

/**
 * @internal class for HTTP request handling.
 */
final class RequestHelper
{
    /**
     * Get the current page from request.
     */
    public static function getCurrentPage(): int
    {
        $pageParam = $_GET['page'] ?? 1;

        return max(1, is_numeric($pageParam) ? (int) $pageParam : 1);
    }

    /**
     * Get the cursor from request.
     */
    public static function getCursor(): ?string
    {
        $cursor = $_GET['cursor'] ?? null;

        return \is_string($cursor) ? $cursor : null;
    }

    /**
     * Get current request path for pagination links.
     */
    public static function getCurrentPath(): string
    {
        if (php_sapi_name() === 'cli') {
            return '';
        }

        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = \is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $requestUri = \is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';

        $parsedPath = parse_url($requestUri, PHP_URL_PATH);
        $path = \is_string($parsedPath) ? $parsedPath : '/';

        return $scheme . '://' . $host . $path;
    }
}
