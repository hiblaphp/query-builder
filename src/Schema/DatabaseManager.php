<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use function Hibla\await;

use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Exceptions\SchemaMigrationException;
use Rcalicdan\ConfigLoader\Config;

/**
 * @phpstan-type TConnectionConfig array{
 *   driver: string,
 *   host?: string,
 *   port?: int|string,
 *   database?: string,
 *   username?: string,
 *   password?: string,
 *   charset?: string,
 *   collation?: string
 * }
 */
class DatabaseManager
{
    private string $driver;
    /** @var TConnectionConfig */
    private array $config;
    private string $connectionName; // Changed: Removed nullable type

    public function __construct(?string $connection = null)
    {
        $dbConfig = Config::get('async-database');

        if (! is_array($dbConfig)) {
            throw new SchemaMigrationException('Invalid database configuration format');
        }

        $connectionName = $connection ?? ($dbConfig['default'] ?? 'mysql');
        if (! is_string($connectionName)) {
            throw new SchemaMigrationException('Connection name must be a string');
        }

        $connections = $dbConfig['connections'] ?? [];
        if (! is_array($connections)) {
            throw new SchemaMigrationException('Connections configuration must be an array');
        }

        $config = $connections[$connectionName] ?? [];
        if (! is_array($config)) {
            throw new SchemaMigrationException("Configuration for '{$connectionName}' connection is invalid");
        }

        /** @var TConnectionConfig $config */
        $this->config = $config;
        $this->driver = strtolower($this->config['driver'] ?? 'mysql');
        $this->connectionName = $connectionName;
    }

    /**
     * Create the configured database if it does not already exist.
     *
     * @throws \RuntimeException If the driver is unsupported or database creation fails.
     */
    public function createDatabaseIfNotExists(): bool
    {
        $database = $this->config['database'] ?? null;

        if (! is_string($database) || $database === '') {
            throw new SchemaMigrationException('Database name not specified or invalid in configuration');
        }

        try {
            return match ($this->driver) {
                'mysql', 'mysqli' => $this->createMySQLDatabase($database),
                'pgsql', 'pgsql_native' => $this->createPostgreSQLDatabase($database),
                'sqlite' => $this->createSQLiteDatabase($database),
                default => throw new SchemaMigrationException("Unsupported driver: {$this->driver}"),
            };
        } catch (\Throwable $e) {
            throw new SchemaMigrationException(
                "Failed to create database '{$database}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function createMySQLDatabase(string $database): bool
    {
        $tempConfig = array_merge($this->config, ['database' => 'mysql']);
        $tempConnectionName = '_temp_' . uniqid();

        DB::addConnection($tempConnectionName, $tempConfig);

        try {
            $charset = $this->config['charset'] ?? 'utf8mb4';
            $collation = $this->config['collation'] ?? 'utf8mb4_unicode_ci';

            $sql = "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}";
            await(DB::connection($tempConnectionName)->rawExecute($sql, []));

            return true;
        } finally {
            DB::removeConnection($tempConnectionName);
        }
    }

    private function createPostgreSQLDatabase(string $database): bool
    {
        $tempConfig = array_merge($this->config, ['database' => 'postgres']);
        $tempConnectionName = '_temp_' . uniqid();

        DB::addConnection($tempConnectionName, $tempConfig);

        try {
            $checkSql = 'SELECT 1 FROM pg_database WHERE datname = $1';
            $exists = await(DB::connection($tempConnectionName)->rawValue($checkSql, [$database]));

            if ($exists === false) {
                $sql = "CREATE DATABASE \"{$database}\"";
                await(DB::connection($tempConnectionName)->rawExecute($sql, []));
            }

            return true;
        } finally {
            DB::removeConnection($tempConnectionName);
        }
    }

    private function createSQLiteDatabase(string $database): bool
    {
        if ($database === ':memory:') {
            return true;
        }

        $directory = dirname($database);

        if (! is_dir($directory) && mkdir($directory, 0755, true) === false) {
            throw new SchemaMigrationException("Failed to create directory: {$directory}");
        }

        if (! file_exists($database)) {
            touch($database);
        }

        return true;
    }

    private function createSQLServerDatabase(string $database): bool
    {
        $tempConfig = array_merge($this->config, ['database' => 'master']);
        $tempConnectionName = '_temp_' . uniqid();

        DB::addConnection($tempConnectionName, $tempConfig);

        try {
            $checkSql = 'SELECT database_id FROM sys.databases WHERE name = ?';
            $exists = await(DB::connection($tempConnectionName)->rawValue($checkSql, [$database]));

            if ($exists === false) {
                $sql = "CREATE DATABASE [{$database}]";
                await(DB::connection($tempConnectionName)->rawExecute($sql, []));
            }

            return true;
        } finally {
            DB::removeConnection($tempConnectionName);
        }
    }

    /**
     * Check if the configured database exists.
     */
    public function databaseExists(): bool
    {
        $database = $this->config['database'] ?? null;

        if (! is_string($database) || $database === '') {
            return false;
        }

        try {
            return match ($this->driver) {
                'mysql', 'mysqli' => $this->checkMySQLDatabase($database),
                'pgsql', 'pgsql_native' => $this->checkPostgreSQLDatabase($database),
                'sqlite' => $this->checkSQLiteDatabase($database),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkMySQLDatabase(string $database): bool
    {
        $tempConfig = array_merge($this->config, ['database' => 'mysql']);
        $tempConnectionName = '_temp_' . uniqid();

        DB::addConnection($tempConnectionName, $tempConfig);

        try {
            $sql = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?';
            $result = await(DB::connection($tempConnectionName)->rawValue($sql, [$database]));

            return (bool) $result;
        } finally {
            DB::removeConnection($tempConnectionName);
        }
    }

    private function checkPostgreSQLDatabase(string $database): bool
    {
        $tempConfig = array_merge($this->config, ['database' => 'postgres']);
        $tempConnectionName = '_temp_' . uniqid();

        DB::addConnection($tempConnectionName, $tempConfig);

        try {
            $sql = 'SELECT 1 FROM pg_database WHERE datname = $1';
            $result = await(DB::connection($tempConnectionName)->rawValue($sql, [$database]));

            return (bool) $result;
        } finally {
            DB::removeConnection($tempConnectionName);
        }
    }

    private function checkSQLiteDatabase(string $database): bool
    {
        return $database === ':memory:' || file_exists($database);
    }

    private function checkSQLServerDatabase(string $database): bool
    {
        $tempConfig = array_merge($this->config, ['database' => 'master']);
        $tempConnectionName = '_temp_' . uniqid();

        DB::addConnection($tempConnectionName, $tempConfig);

        try {
            $sql = 'SELECT database_id FROM sys.databases WHERE name = ?';
            $result = await(DB::connection($tempConnectionName)->rawValue($sql, [$database]));

            return (bool) $result;
        } finally {
            DB::removeConnection($tempConnectionName);
        }
    }

    /**
     * Get the current driver name.
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Get the connection name.
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }
}
