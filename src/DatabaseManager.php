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
use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
use Hibla\Sql\SqlClientInterface;
use Rcalicdan\ConfigLoader\Config;

/**
 * Manages the connection pool registry, lazy-loading clients,
 * and bootstrapping environment configurations.
 */
class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * Named connection instances.
     *
     * @var array<string, DatabaseConnectionInterface>
     */
    private array $connections = [];

    /**
     * The default connection name.
     */
    private ?string $defaultConnectionName = null;

    /**
     * Create a new DatabaseManager instance.
     */
    public function __construct()
    {
        // Inject this manager as the static resolver for QueryBuilder instantiation.
        QueryBuilder::setConnectionResolver($this);

        // Boot up and configure custom pagination template paths if defined.
        $this->configurePaginationTemplates();
    }

    /**
     * Parse the configuration file and configure custom pagination templates if specified.
     *
     * @return void
     */
    private function configurePaginationTemplates(): void
    {
        try {
            $dbConfig = Config::loadFromRoot('hibla-database');

            if (! \is_array($dbConfig)) {
                return;
            }

            /** @var array<string, mixed> $typedConfig */
            $typedConfig = $dbConfig;
            $paginationConfig = $typedConfig['pagination'] ?? [];

            if (! \is_array($paginationConfig)) {
                return;
            }

            $templatesPath = $paginationConfig['templates_path'] ?? null;

            if (\is_string($templatesPath) && trim($templatesPath) !== '' && is_dir($templatesPath)) {
                Paginator::setTemplatesPath($templatesPath);
                CursorPaginator::setTemplatesPath($templatesPath);
            }
        } catch (\Throwable $e) {
            // Silently degrade to built-in templates if config cannot be loaded
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws DatabaseConfigNotFoundException If the configuration file is missing.
     * @throws InvalidConnectionConfigException If the specified connection is malformed.
     */
    public function connection(?string $name = null): DatabaseConnectionInterface
    {
        $name = $name ?? $this->getDefaultConnectionName();

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->initializeFromConfig($name);
    }

    /**
     * {@inheritdoc}
     */
    public function addConnection(string $name, DatabaseConnectionInterface $connection): void
    {
        $this->connections[$name] = $connection;
        if ($this->defaultConnectionName === null) {
            $this->defaultConnectionName = $name;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeConnection(string $name): void
    {
        if (isset($this->connections[$name])) {
            $this->connections[$name]->getClient()->close();
            unset($this->connections[$name]);
        }

        if ($this->defaultConnectionName === $name) {
            $this->defaultConnectionName = null;
        }
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
                maxConnections: (int) ($config['max_connections'] ?? 10),
                idleTimeout: (int) ($config['idle_timeout'] ?? 60),
                maxLifetime: (int) ($config['max_lifetime'] ?? 3600),
                statementCacheSize: (int) ($config['statement_cache_size'] ?? 256),
                enableStatementCache: (bool) ($config['enable_statement_cache'] ?? true),
                maxWaiters: (int) ($config['max_waiters'] ?? 0),
                acquireTimeout: (float) ($config['acquire_timeout'] ?? 10.0),
            ),

            default => throw new \InvalidArgumentException("Driver '{$driver}' is not supported yet."),
        };
    }

    /**
     * Get the default connection name from configuration.
     *
     * @return string
     *
     * @throws DatabaseConfigNotFoundException
     * @throws InvalidConnectionConfigException
     */
    public function getDefaultConnectionName(): string
    {
        if ($this->defaultConnectionName !== null) {
            return $this->defaultConnectionName;
        }

        $dbConfigAll = Config::loadFromRoot('hibla-database');

        if (! \is_array($dbConfigAll)) {
            throw new DatabaseConfigNotFoundException();
        }

        $defaultConnection = $dbConfigAll['default'] ?? null;
        if (! \is_string($defaultConnection)) {
            throw new InvalidConnectionConfigException('Default connection name must be a string.');
        }

        $this->defaultConnectionName = $defaultConnection;

        return $defaultConnection;
    }

    /**
     * Initialize a database connection dynamically from configuration files.
     *
     * @throws DatabaseConfigNotFoundException
     * @throws InvalidConnectionConfigException
     */
    private function initializeFromConfig(string $name): DatabaseConnectionInterface
    {
        $dbConfigAll = Config::loadFromRoot('hibla-database');

        if (! \is_array($dbConfigAll)) {
            throw new DatabaseConfigNotFoundException();
        }

        $connections = $dbConfigAll['connections'] ?? null;
        if (! \is_array($connections)) {
            throw new InvalidConnectionConfigException('Database connections configuration must be an array.');
        }

        if (! isset($connections[$name]) || ! \is_array($connections[$name])) {
            throw new InvalidConnectionConfigException("Connection '{$name}' not found in configuration.");
        }

        /** @var array<string, mixed> $connectionConfig */
        $connectionConfig = $connections[$name];
        $driver = $connectionConfig['driver'] ?? 'mysql';

        $client = $this->resolveClientFromConfig($connectionConfig);
        $connection = new DatabaseConnection($client, $driver);

        $this->connections[$name] = $connection;

        return $connection;
    }

    /**
     * Start a new query builder on the default connection.
     */
    public function table(string $table): QueryBuilderInterface
    {
        return $this->connection()->table($table);
    }

    /**
     * Execute a raw query and return all rows.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>|array<int, object>>
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->raw($sql, $bindings);
    }

    /**
     * Execute a raw query and return the first result.
     *
     * @return PromiseInterface<array<string, mixed>|object|null>
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->rawFirst($sql, $bindings);
    }

    /**
     * Execute a raw query and return a single scalar value.
     *
     * @return PromiseInterface<mixed>
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->rawValue($sql, $bindings);
    }

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE).
     *
     * @return PromiseInterface<int>
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection()->rawExecute($sql, $bindings);
    }

    /**
     * Execute an auto-managed transaction.
     *
     * @template TResult
     *
     * @param callable(Interfaces\DatabaseTransactionInterface): TResult $callback
     *
     * @return PromiseInterface<TResult>
     */
    public function transaction(callable $callback): PromiseInterface
    {
        return $this->connection()->transaction($callback);
    }

    /**
     * Begin a manual transaction.
     *
     * @return PromiseInterface<Interfaces\DatabaseTransactionInterface>
     */
    public function beginTransaction(): PromiseInterface
    {
        return $this->connection()->beginTransaction();
    }
}
