<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
use Rcalicdan\ConfigLoader\Config;

/**
 * Handles configuration and driver detection for the query builder.
 */
class BuilderConfiguration
{
    private static ?string $cachedDriver = null;

    private static bool $driverDetected = false;

    private static bool $templatesConfigured = false;

    /**
     * Auto-detect and cache the database driver.
     */
    public static function detectDriver(): string
    {
        if (self::$driverDetected && self::$cachedDriver !== null) {
            return self::$cachedDriver;
        }

        try {
            $driver = self::getDriverFromConfig();
            $detectedDriver = $driver !== null ? strtolower($driver) : 'mysql';

            self::$cachedDriver = $detectedDriver;
            self::$driverDetected = true;

            return $detectedDriver;
        } catch (\Throwable $e) {
            self::$cachedDriver = 'mysql';
            self::$driverDetected = true;

            return 'mysql';
        }
    }

    /**
     * Configure pagination templates from config.
     */
    public static function configureTemplates(): void
    {
        if (self::$templatesConfigured) {
            return;
        }

        try {
            $dbConfig = Config::get('async-database');

            if (! \is_array($dbConfig)) {
                return;
            }

            $paginationConfig = $dbConfig['pagination'] ?? [];
            if (! \is_array($paginationConfig)) {
                return;
            }

            $templatesPath = $paginationConfig['templates_path'] ?? null;

            if (\is_string($templatesPath) && trim($templatesPath) !== '' && is_dir($templatesPath)) {
                Paginator::setTemplatesPath($templatesPath);
                CursorPaginator::setTemplatesPath($templatesPath);
            }

            self::$templatesConfigured = true;
        } catch (\Throwable $e) {
            error_log('Failed to configure pagination templates: ' . $e->getMessage());
        }
    }

    /**
     * Reset all cached configuration.
     */
    public static function reset(): void
    {
        self::$cachedDriver = null;
        self::$driverDetected = false;
        self::$templatesConfigured = false;
    }

    /**
     * Get the driver from the loaded configuration.
     */
    private static function getDriverFromConfig(): ?string
    {
        $dbConfig = Config::get('async-database');

        if (! \is_array($dbConfig)) {
            return null;
        }

        $defaultConnection = $dbConfig['default'] ?? null;
        if (! \is_string($defaultConnection)) {
            return null;
        }

        $connections = $dbConfig['connections'] ?? [];
        if (! \is_array($connections)) {
            return null;
        }

        $connectionConfig = $connections[$defaultConnection] ?? null;
        if (! \is_array($connectionConfig)) {
            return null;
        }

        $driver = $connectionConfig['driver'] ?? null;

        return \is_string($driver) ? $driver : null;
    }
}
