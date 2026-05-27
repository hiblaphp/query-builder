<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Internals;

use Hibla\Mysql\MysqlClient;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Exceptions\DatabaseConfigNotFoundException;
use Hibla\QueryBuilder\Exceptions\InvalidConnectionConfigException;
use Hibla\QueryBuilder\Interfaces\ConnectionResolverInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;
use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
use Hibla\QueryBuilder\QueryBuilder;
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
     *
     * @throws InvalidConnectionConfigException If the driver is unsupported.
     */
    public function resolveClientFromConfig(array $config): SqlClientInterface
    {
        $driver = strtolower(\is_string($config['driver'] ?? null) ? $config['driver'] : 'mysql');

        if ($driver === 'mysql') {
            $minVal = $config['min_connections'] ?? null;
            $minConnections = is_numeric($minVal) ? (int) $minVal : 0;

            $maxVal = $config['max_connections'] ?? null;
            $maxConnections = is_numeric($maxVal) ? (int) $maxVal : 10;

            $idleVal = $config['idle_timeout'] ?? null;
            $idleTimeout = is_numeric($idleVal) ? (int) $idleVal : 60;

            $lifetimeVal = $config['max_lifetime'] ?? null;
            $maxLifetime = is_numeric($lifetimeVal) ? (int) $lifetimeVal : 3600;

            $cacheSizeVal = $config['statement_cache_size'] ?? null;
            $statementCacheSize = is_numeric($cacheSizeVal) ? (int) $cacheSizeVal : 256;

            $enableStatementCache = (bool) ($config['enable_statement_cache'] ?? true);

            $waitersVal = $config['max_waiters'] ?? null;
            $maxWaiters = is_numeric($waitersVal) ? (int) $waitersVal : 0;

            $timeoutVal = $config['acquire_timeout'] ?? null;
            $acquireTimeout = is_numeric($timeoutVal) ? (float) $timeoutVal : 10.0;

            return new MysqlClient(
                config: $config,
                minConnections: $minConnections,
                maxConnections: $maxConnections,
                idleTimeout: $idleTimeout,
                maxLifetime: $maxLifetime,
                statementCacheSize: $statementCacheSize,
                enableStatementCache: $enableStatementCache,
                maxWaiters: $maxWaiters,
                acquireTimeout: $acquireTimeout,
            );
        }

        throw new InvalidConnectionConfigException("Driver '{$driver}' is not supported yet.");
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
        $driver = \is_string($connectionConfig['driver'] ?? null) ? $connectionConfig['driver'] : 'mysql';

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
     * @param array<int, mixed> $bindings
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
     * @param array<int, mixed> $bindings
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
     * @param array<int, mixed> $bindings
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
     * @param array<int, mixed> $bindings
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
     * @param callable(DatabaseTransactionInterface): TResult $callback
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
     * @return PromiseInterface<DatabaseTransactionInterface>
     */
    public function beginTransaction(): PromiseInterface
    {
        return $this->connection()->beginTransaction();
    }
}
