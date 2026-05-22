<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Mysql\MysqlClient;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Exceptions\DatabaseConfigNotFoundException;
use Hibla\QueryBuilder\Exceptions\InvalidConnectionConfigException;
use Hibla\QueryBuilder\Interfaces\ConnectionResolverInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\Sql\SqlClientInterface;
use Rcalicdan\ConfigLoader\Config;

/**
 * @internal Do not use this directly
 */
class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * @var array<string, DatabaseConnectionInterface>
     */
    private array $connections = [];

    private ?string $defaultConnectionName = null;

    public function __construct()
    {
        QueryBuilder::setConnectionResolver($this);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveClientFromConfig(array $config): SqlClientInterface
    {
        $driver = strtolower($config['driver'] ?? 'mysql');

        return match ($driver) {
            'mysql' => new MysqlClient(
                config: $config,
                minConnections: (int) ($config['min_connections'] ?? 0),
                maxConnections: (int) ($config['pool_size'] ?? $config['max_connections'] ?? 10),
                idleTimeout: (int) ($config['idle_timeout'] ?? 60),
                maxLifetime: (int) ($config['max_lifetime'] ?? 3600),
                statementCacheSize: (int) ($config['statement_cache_size'] ?? 256),
                enableStatementCache: (bool) ($config['enable_statement_cache'] ?? true),
                maxWaiters: (int) ($config['max_waiters'] ?? 0),
                acquireTimeout: (float) ($config['acquire_timeout'] ?? 10.0),
            ),

            // 'pgsql' => new PgsqlClient(...), // Ready for future async Postgres client
            // 'sqlite' => new SqliteClient(...), // Ready for future async SQLite client

            default => throw new \InvalidArgumentException("Driver '{$driver}' is not supported yet."),
        };
    }

    /**
     * Get or initialize a database connection by name.
     */
    public function connection(?string $name = null): DatabaseConnectionInterface
    {
        $name ??= $this->getDefaultConnectionName();

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->initializeFromConfig($name);
    }

    /**
     * Add a fully configured connection manually.
     */
    public function addConnection(string $name, DatabaseConnectionInterface $connection): void
    {
        $this->connections[$name] = $connection;
        if ($this->defaultConnectionName === null) {
            $this->defaultConnectionName = $name;
        }
    }

    /**
     * Get the default connection name, loading from config if necessary.
     */
    public function getDefaultConnectionName(): string
    {
        if ($this->defaultConnectionName !== null) {
            return $this->defaultConnectionName;
        }

        $dbConfig = Config::get('async-database');
        if (! \is_array($dbConfig)) {
            throw new DatabaseConfigNotFoundException();
        }

        $default = $dbConfig['default'] ?? null;
        if (! \is_string($default)) {
            throw new InvalidConnectionConfigException('Default connection name must be a string.');
        }

        $this->defaultConnectionName = $default;

        return $default;
    }

    /**
     * Initialize a connection dynamically from configuration files.
     */
    private function initializeFromConfig(string $name): DatabaseConnectionInterface
    {
        $dbConfig = Config::get('async-database');

        $connections = $dbConfig['connections'] ?? null;
        if (! \is_array($connections) || ! isset($connections[$name])) {
            throw new InvalidConnectionConfigException("Connection '{$name}' not found in configuration.");
        }

        $config = $connections[$name];
        $driver = strtolower($config['driver'] ?? 'mysql');
        $poolSize = (int) ($config['pool_size'] ?? 10);

        $client = match ($driver) {
            'mysql', 'mysqli' => new MysqlClient($config, maxConnections: $poolSize),
            // PostgreSQL and SQLite will be added here in the future
            default => throw new \InvalidArgumentException("Driver '{$driver}' is not supported yet."),
        };

        $connection = new DatabaseConnection($client, $driver);
        $this->connections[$name] = $connection;

        return $connection;
    }

    public function table(string $table): QueryBuilderInterface
    {
        return $this->connection()->table($table);
    }

    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->raw($sql, $bindings);
    }

    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->rawFirst($sql, $bindings);
    }

    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->rawValue($sql, $bindings);
    }

    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->rawExecute($sql, $bindings);
    }

    public function transaction(callable $callback): PromiseInterface
    {
        return $this->connection()->transaction($callback);
    }

    public function beginTransaction(): PromiseInterface
    {
        return $this->connection()->beginTransaction();
    }
}
