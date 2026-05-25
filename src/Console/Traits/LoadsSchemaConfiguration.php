<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console\Traits;

use Rcalicdan\ConfigLoader\Config;

trait LoadsSchemaConfiguration
{
    /**
     * Get the schema configuration for the specified connection.
     *
     * @param string|null $connection The connection name, or null for default configuration
     *
     * @return array{
     *     schema_path: string,
     *     migrations_path: string,
     *     migrations_table: string,
     *     naming_convention: string,
     *     timezone: string,
     *     recursive: bool,
     *     connection_paths: array<string, string>
     * } The schema configuration array
     */
    private function getSchemaConfig(?string $connection = null): array
    {
        $defaults = $this->getDefaultSchemaConfig();
        $loadedConfig = $this->loadConfigSafely($connection);
        $finalConfig = array_merge($defaults, $loadedConfig);

        return [
            'schema_path' => \is_string($finalConfig['schema_path'] ?? null) ? $finalConfig['schema_path'] : $defaults['schema_path'],
            'migrations_path' => \is_string($finalConfig['migrations_path'] ?? null) ? $finalConfig['migrations_path'] : $defaults['migrations_path'],
            'migrations_table' => \is_string($finalConfig['migrations_table'] ?? null) ? $finalConfig['migrations_table'] : $defaults['migrations_table'],
            'naming_convention' => \is_string($finalConfig['naming_convention'] ?? null) ? $finalConfig['naming_convention'] : $defaults['naming_convention'],
            'timezone' => \is_string($finalConfig['timezone'] ?? null) ? $finalConfig['timezone'] : $defaults['timezone'],
            'recursive' => isset($finalConfig['recursive']) ? (bool) $finalConfig['recursive'] : $defaults['recursive'],
            'connection_paths' => $this->normalizeConnectionPaths($finalConfig['connection_paths'] ?? null, $defaults['connection_paths']),
        ];
    }

    /**
     * Load configuration safely, catching any exceptions.
     *
     * @param string|null $connection The connection name to load configuration for
     *
     * @return array<string, mixed> The loaded configuration array, or empty array on failure
     */
    private function loadConfigSafely(?string $connection): array
    {
        try {
            $config = Config::loadFromRoot('hibla-migrations');

            if (! \is_array($config)) {
                return [];
            }

            /** @var array<string, mixed> $baseConfig */
            $baseConfig = $config;

            if ($connection === null) {
                return $baseConfig;
            }

            /** @var array<string, mixed> $connectionConfig */
            $connectionConfig = $this->getConnectionConfig($baseConfig, $connection);

            /** @var array<string, mixed> */
            return array_merge($baseConfig, $connectionConfig);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get the configuration for a specific connection.
     *
     * @param array<string, mixed> $config The full configuration array
     * @param string $connection The connection name
     *
     * @return array<string, mixed> The connection-specific configuration, or empty array if not found
     */
    private function getConnectionConfig(array $config, string $connection): array
    {
        $connections = $config['connections'] ?? null;

        if (! \is_array($connections)) {
            return [];
        }

        /** @var array<string, mixed> $connectionsTyped */
        $connectionsTyped = $connections;
        $connectionConfig = $connectionsTyped[$connection] ?? null;

        if (! \is_array($connectionConfig)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $connectionConfig;
    }

    /**
     * Normalize connection paths to ensure proper typing.
     *
     * @param mixed $connectionPaths
     * @param array<string, string> $defaults
     *
     * @return array<string, string>
     */
    private function normalizeConnectionPaths($connectionPaths, array $defaults): array
    {
        if (! \is_array($connectionPaths)) {
            return $defaults;
        }

        $normalized = [];
        foreach ($connectionPaths as $key => $value) {
            if (\is_string($key) && \is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @return array{
     *     schema_path: string,
     *     migrations_path: string,
     *     migrations_table: string,
     *     naming_convention: string,
     *     timezone: string,
     *     auto_migrate: bool,
     *     recursive: bool,
     *     connection_paths: array<string, string>
     * }
     */
    private function getDefaultSchemaConfig(): array
    {
        $projectRoot = isset($this->projectRoot) && \is_string($this->projectRoot)
            ? $this->projectRoot
            : '.';

        return [
            'schema_path' => $projectRoot . '/database/schema',
            'migrations_path' => $projectRoot . '/database/migrations',
            'migrations_table' => 'migrations',
            'naming_convention' => 'timestamp',
            'timezone' => 'UTC',
            'auto_migrate' => false,
            'recursive' => true,
            'connection_paths' => [],
        ];
    }

    /**
     * Get the migrations path for a specific connection.
     * Supports subdirectories for connection-specific organization.
     */
    private function getMigrationsPath(?string $connection = null): string
    {
        $config = $this->getSchemaConfig($connection);
        $basePath = $config['migrations_path'];

        $projectRoot = isset($this->projectRoot) && \is_string($this->projectRoot)
            ? $this->projectRoot
            : '.';

        if (! $this->isAbsolutePath($basePath)) {
            $basePath = $projectRoot . '/' . ltrim($basePath, '/');
        }

        if ($connection === null) {
            return $basePath;
        }

        $connectionPaths = $config['connection_paths'];

        if (isset($connectionPaths[$connection])) {
            $subPath = $connectionPaths[$connection];
            if (\is_string($subPath) && $subPath !== '') {
                return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . trim($subPath, '/\\');
            }
        }

        return $basePath;
    }

    private function getMigrationsTable(?string $connection = null): string
    {
        $config = $this->getSchemaConfig($connection);

        return $config['migrations_table'];
    }

    private function getNamingConvention(?string $connection = null): string
    {
        $config = $this->getSchemaConfig($connection);

        return $config['naming_convention'];
    }

    private function getTimezone(?string $connection = null): string
    {
        $config = $this->getSchemaConfig($connection);

        return $config['timezone'];
    }

    /**
     * Check if recursive migration discovery is enabled.
     */
    private function isRecursiveEnabled(?string $connection = null): bool
    {
        $config = $this->getSchemaConfig($connection);

        return $config['recursive'];
    }

    /**
     * Get all migration files recursively or non-recursively based on configuration.
     *
     * @return list<string>
     */
    private function getAllMigrationFiles(?string $connection = null): array
    {
        $migrationsPath = $this->getMigrationsPath($connection);

        if (! is_dir($migrationsPath)) {
            return [];
        }

        if ($this->isRecursiveEnabled($connection)) {
            return $this->getMigrationFilesRecursive($migrationsPath);
        }

        return $this->getMigrationFilesFlat($migrationsPath);
    }

    /**
     * Get migration files from a single directory (non-recursive).
     *
     * @return list<string>
     */
    private function getMigrationFilesFlat(string $directory): array
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * Get migration files recursively from all subdirectories.
     *
     * @return list<string>
     */
    private function getMigrationFilesRecursive(string $directory): array
    {
        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Throwable $e) {
            return $this->getMigrationFilesFlat($directory);
        }

        sort($files);

        return $files;
    }

    /**
     * Get the relative path of a migration file from the base migrations directory.
     * This is used for storing migration paths in the database.
     */
    private function getRelativeMigrationPath(string $filePath, ?string $connection = null): string
    {
        $basePath = $this->getMigrationsPath($connection);

        $normalizedBasePath = $this->normalizePath($basePath);
        $normalizedFilePath = $this->normalizePath($filePath);

        if (str_starts_with($normalizedFilePath, $normalizedBasePath)) {
            $relativePath = substr($normalizedFilePath, \strlen($normalizedBasePath));
            $relativePath = ltrim($relativePath, '/\\');
        } else {
            $relativePath = basename($filePath);
        }

        return str_replace('\\', '/', $relativePath);
    }

    /**
     * Get the full path of a migration file from its relative path.
     * This is used for loading migration files from the database.
     */
    private function getFullMigrationPath(string $relativePath, ?string $connection = null): string
    {
        $basePath = $this->getMigrationsPath($connection);

        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        return $basePath . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    /**
     * Normalize a file path for consistent comparison across platforms.
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = rtrim($path, '/');

        return $path;
    }

    /**
     * Check if a path is absolute.
     */
    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/') {
            return true;
        }

        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    private function ensureDirectoryExists(string $path): bool
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (is_dir($path)) {
            return true;
        }

        $result = @mkdir($path, 0755, true);

        if ($result === false) {
            $error = error_get_last();
            $this->io->error("Failed to create directory: {$path}");

            if ($error !== null) {
                $this->io->error('Error: ' . $error['message']);
            }

            $parentDir = \dirname($path);
            $this->io->note("Parent directory: {$parentDir}");
            $this->io->note('Parent directory exists: ' . (is_dir($parentDir) ? 'Yes' : 'No'));
            $this->io->note('Parent directory writable: ' . (is_writable($parentDir) ? 'Yes' : 'No'));

            return false;
        }

        return true;
    }

    /**
     * Get migration files that match a specific pattern.
     * Useful for filtering migrations by prefix or subdirectory.
     *
     * @return list<string>
     */
    private function getFilteredMigrationFiles(string $pattern, ?string $connection = null): array
    {
        $allFiles = $this->getAllMigrationFiles($connection);

        return array_values(array_filter($allFiles, function ($file) use ($pattern) {
            $relativePath = $this->getRelativeMigrationPath($file, null);

            return fnmatch($pattern, $relativePath);
        }));
    }
}