<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Sql\SqlClientInterface;

/**
 * Acts as a bridge between concrete QueryBuilder instances and the DatabaseManager,
 * preventing circular dependencies while allowing dynamic connection resolution.
 */
interface ConnectionResolverInterface
{
    /**
     * Get a connection by name (or default if null).
     */
    public function connection(?string $name = null): DatabaseConnectionInterface;

    /**
     * Resolve an ad-hoc configuration array into a raw SqlClient.
     *
     * @param array<string, mixed> $config
     */
    public function resolveClientFromConfig(array $config): SqlClientInterface;
}
