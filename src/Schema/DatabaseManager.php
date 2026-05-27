<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\DatabaseConnection;
use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Exceptions\DatabaseConfigurationException;
use Hibla\QueryBuilder\Exceptions\InvalidConnectionConfigException;
use Hibla\QueryBuilder\Exceptions\SchemaMigrationException;
use Rcalicdan\ConfigLoader\Config;

use function Hibla\async;
use function Hibla\await;

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

    /**
     * @var TConnectionConfig
     */
    private array $config;

    private string $connectionName;

    public function __construct(?string $connection = null)
    {
        $dbConfig = Config::loadFromRoot('hibla-database');

        if (! \is_array($dbConfig)) {
            throw new DatabaseConfigurationException('Invalid database configuration format');
        }

        $connectionName = $connection ?? ($dbConfig['default'] ?? 'mysql');
        if (! \is_string($connectionName)) {
            throw new InvalidConnectionConfigException('Connection name must be a string');
        }

        $connections = $dbConfig['connections'] ?? [];
        if (! \is_array($connections)) {
            throw new DatabaseConfigurationException('Connections configuration must be an array');
        }

        $config = $connections[$connectionName] ?? [];
        if (! \is_array($config)) {
            throw new InvalidConnectionConfigException("Configuration for '{$connectionName}' connection is invalid");
        }

        /** @var TConnectionConfig $config */
        $this->config = $config;
        $this->driver = strtolower($this->config['driver'] ?? 'mysql');
        $this->connectionName = $connectionName;
    }

    /**
     * Create the configured database if it does not already exist.
     *
     * @return PromiseInterface<bool>
     *
     * @throws \RuntimeException If the driver is unsupported or database creation fails.
     */
    public function createDatabaseIfNotExists(): PromiseInterface
    {
        return async(function () {
            $database = $this->config['database'] ?? null;

            if (! \is_string($database) || $database === '') {
                throw new SchemaMigrationException('Database name not specified or invalid in configuration');
            }

            try {
                return match ($this->driver) {
                    'mysql', 'mysqli' => await($this->createMySQLDatabase($database)),
                    'pgsql', 'pgsql_native' => await($this->createPostgreSQLDatabase($database)),
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
        });
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function createMySQLDatabase(string $database): PromiseInterface
    {
        return async(function () use ($database) {
            $tempConfig = $this->config;
            $tempConfig['database'] = 'information_schema';

            $tempConnectionName = '_temp_' . uniqid();
            $client = DB::resolveClientFromConfig($tempConfig);

            DB::addConnection(
                $tempConnectionName,
                new DatabaseConnection($client, $this->driver)
            );

            try {
                $charset = $this->config['charset'] ?? 'utf8mb4';
                $collation = $this->config['collation'] ?? 'utf8mb4_unicode_ci';

                $sql = "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}";
                await(DB::connection($tempConnectionName)->rawExecute($sql, []));

                return true;
            } finally {
                DB::removeConnection($tempConnectionName);
            }
        });
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function createPostgreSQLDatabase(string $database): PromiseInterface
    {
        return async(function () use ($database) {
            $tempConfig = array_merge($this->config, ['database' => 'postgres']);
            $tempConnectionName = '_temp_' . uniqid();

            $client = DB::resolveClientFromConfig($tempConfig);

            DB::addConnection(
                $tempConnectionName,
                new DatabaseConnection($client, $this->driver)
            );

            try {
                $checkSql = 'SELECT 1 FROM pg_database WHERE datname = ?';
                $exists = await(DB::connection($tempConnectionName)->rawValue($checkSql, [$database]));

                if ($exists === false || $exists === null) {
                    $sql = "CREATE DATABASE \"{$database}\"";
                    await(DB::connection($tempConnectionName)->rawExecute($sql, []));
                }

                return true;
            } finally {
                DB::removeConnection($tempConnectionName);
            }
        });
    }

    private function createSQLiteDatabase(string $database): bool
    {
        if ($database === ':memory:') {
            return true;
        }

        $directory = \dirname($database);

        if (! is_dir($directory) && mkdir($directory, 0755, true) === false) {
            throw new SchemaMigrationException("Failed to create directory: {$directory}");
        }

        if (! file_exists($database)) {
            touch($database);
        }

        return true;
    }

    /**
     * Check if the configured database exists.
     *
     * @return PromiseInterface<bool>
     */
    public function databaseExists(): PromiseInterface
    {
        return async(function () {
            $database = $this->config['database'] ?? null;

            if (! \is_string($database) || $database === '') {
                return false;
            }

            try {
                return match ($this->driver) {
                    'mysql', 'mysqli' => await($this->checkMySQLDatabase($database)),
                    'pgsql', 'pgsql_native' => await($this->checkPostgreSQLDatabase($database)),
                    'sqlite' => $this->checkSQLiteDatabase($database),
                    default => false,
                };
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function checkMySQLDatabase(string $database): PromiseInterface
    {
        return async(function () use ($database) {
            $tempConfig = $this->config;
            $tempConfig['database'] = 'information_schema';

            $tempConnectionName = '_temp_' . uniqid();

            $client = DB::resolveClientFromConfig($tempConfig);
            DB::addConnection(
                $tempConnectionName,
                new DatabaseConnection($client, $this->driver)
            );

            try {
                $sql = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?';
                $result = await(DB::connection($tempConnectionName)->rawValue($sql, [$database]));

                return (bool) $result;
            } finally {
                DB::removeConnection($tempConnectionName);
            }
        });
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function checkPostgreSQLDatabase(string $database): PromiseInterface
    {
        return async(function () use ($database) {
            $tempConfig = array_merge($this->config, ['database' => 'postgres']);
            $tempConnectionName = '_temp_' . uniqid();

            $client = DB::resolveClientFromConfig($tempConfig);
            DB::addConnection(
                $tempConnectionName,
                new DatabaseConnection($client, $this->driver)
            );

            try {
                $sql = 'SELECT 1 FROM pg_database WHERE datname = ?';
                $result = await(DB::connection($tempConnectionName)->rawValue($sql, [$database]));

                return (bool) $result;
            } finally {
                DB::removeConnection($tempConnectionName);
            }
        });
    }

    private function checkSQLiteDatabase(string $database): bool
    {
        return $database === ':memory:' || file_exists($database);
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
