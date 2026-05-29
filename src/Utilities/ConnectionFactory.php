<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

use Hibla\Mysql\MysqlClient;
use Hibla\Postgres\PostgresClient;
use Hibla\QueryBuilder\Exceptions\InvalidConnectionConfigException;
use Hibla\Sql\SqlClientInterface;

/**
 * @internal Factory responsible for parsing database configurations and instantiating SQL clients.
 */
final class ConnectionFactory
{
    /**
     * Resolves a raw configuration array and instantiates the matching database client.
     *
     * @param array<string, mixed> $config
     *
     * @throws InvalidConnectionConfigException
     */
    public static function make(array $config): SqlClientInterface
    {
        $driver = strtolower(\is_string($config['driver'] ?? null) ? $config['driver'] : 'mysql');

        if ($driver !== 'mysql' && $driver !== 'pgsql' && $driver !== 'postgres') {
            throw new InvalidConnectionConfigException("Driver '{$driver}' is not supported yet.");
        }

        $poolSettings = self::extractPoolSettings($config);

        if ($driver === 'mysql') {
            return self::resolveMysqlClient($config, $poolSettings);
        }

        return self::resolvePostgresClient($config, $poolSettings);
    }

    /**
     * Extracts the shared connection pool configurations safely.
     *
     * @param array<string, mixed> $config
     * @return array{
     *     min_connections: int,
     *     max_connections: int,
     *     idle_timeout: int,
     *     max_lifetime: int,
     *     statement_cache_size: int,
     *     enable_statement_cache: bool,
     *     max_waiters: int,
     *     acquire_timeout: float
     * }
     */
    private static function extractPoolSettings(array $config): array
    {
        $minVal = $config['min_connections'] ?? null;
        $maxVal = $config['max_connections'] ?? null;
        $idleVal = $config['idle_timeout'] ?? null;
        $lifetimeVal = $config['max_lifetime'] ?? null;
        $cacheSizeVal = $config['statement_cache_size'] ?? null;
        $waitersVal = $config['max_waiters'] ?? null;
        $timeoutVal = $config['acquire_timeout'] ?? null;

        return [
            'min_connections' => is_numeric($minVal) ? (int) $minVal : 0,
            'max_connections' => is_numeric($maxVal) ? (int) $maxVal : 10,
            'idle_timeout' => is_numeric($idleVal) ? (int) $idleVal : 60,
            'max_lifetime' => is_numeric($lifetimeVal) ? (int) $lifetimeVal : 3600,
            'statement_cache_size' => is_numeric($cacheSizeVal) ? (int) $cacheSizeVal : 256,
            'enable_statement_cache' => (bool) ($config['enable_statement_cache'] ?? true),
            'max_waiters' => is_numeric($waitersVal) ? (int) $waitersVal : 0,
            'acquire_timeout' => is_numeric($timeoutVal) ? (float) $timeoutVal : 10.0,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array{
     *     min_connections: int,
     *     max_connections: int,
     *     idle_timeout: int,
     *     max_lifetime: int,
     *     statement_cache_size: int,
     *     enable_statement_cache: bool,
     *     max_waiters: int,
     *     acquire_timeout: float
     * } $pool
     */
    private static function resolveMysqlClient(array $config, array $pool): SqlClientInterface
    {
        return new MysqlClient(
            config: $config,
            minConnections: $pool['min_connections'],
            maxConnections: $pool['max_connections'],
            idleTimeout: $pool['idle_timeout'],
            maxLifetime: $pool['max_lifetime'],
            statementCacheSize: $pool['statement_cache_size'],
            enableStatementCache: $pool['enable_statement_cache'],
            maxWaiters: $pool['max_waiters'],
            acquireTimeout: $pool['acquire_timeout'],
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array{
     *     min_connections: int,
     *     max_connections: int,
     *     idle_timeout: int,
     *     max_lifetime: int,
     *     statement_cache_size: int,
     *     enable_statement_cache: bool,
     *     max_waiters: int,
     *     acquire_timeout: float
     * } $pool
     */
    private static function resolvePostgresClient(array $config, array $pool): SqlClientInterface
    {
        $pgConfig = [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 5432,
            'username' => $config['username'] ?? 'postgres',
            'password' => $config['password'] ?? '',
            'database' => $config['database'] ?? '',
            'sslmode' => $config['sslmode'] ?? 'prefer',
            'ssl_ca' => $config['ssl_ca'] ?? null,
            'ssl_cert' => $config['ssl_cert'] ?? null,
            'ssl_key' => $config['ssl_key'] ?? null,
            'connect_timeout' => $config['connect_timeout'] ?? 10,
            'application_name' => $config['application_name'] ?? 'hibla_pgsql',
            'kill_timeout_seconds' => $config['kill_timeout_seconds'] ?? 3.0,
            'enable_server_side_cancellation' => $config['enable_server_side_cancellation'] ?? false,
            'reset_connection' => $config['reset_connection'] ?? false,
            'cast_prepared_types' => $config['cast_prepared_types'] ?? true,
        ];

        return new PostgresClient(
            config: $pgConfig,
            minConnections: $pool['min_connections'],
            maxConnections: $pool['max_connections'],
            idleTimeout: $pool['idle_timeout'],
            maxLifetime: $pool['max_lifetime'],
            statementCacheSize: $pool['statement_cache_size'],
            enableStatementCache: $pool['enable_statement_cache'],
            maxWaiters: $pool['max_waiters'],
            acquireTimeout: $pool['acquire_timeout'],
        );
    }
}