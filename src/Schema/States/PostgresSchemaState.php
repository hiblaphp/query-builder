<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

class PostgresSchemaState extends SchemaState
{
    public function dump(array $config, string $path, string $migrationsTable): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 5432);
        $user = $config['username'] ?? 'postgres';
        $db = $config['database'] ?? '';

        $env = isset($config['password']) && $config['password'] !== ''
            ? ['PGPASSWORD' => $config['password']]
            : [];

        // 1. Dump Schema
        $this->executeCommandAndWriteToFile(
            ['pg_dump', '-h', $host, '-p', $port, '-U', $user, '--schema-only', '--no-owner', '--no-privileges', $db],
            $path,
            false,
            $env
        );

        // 2. Dump Migrations Table Data
        $this->executeCommandAndWriteToFile(
            ['pg_dump', '-h', $host, '-p', $port, '-U', $user, '--data-only', '-t', $migrationsTable, $db],
            $path,
            true, // append
            $env
        );
    }

    public function load(array $config, string $path): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 5432);
        $user = $config['username'] ?? 'postgres';
        $db = $config['database'] ?? '';

        $env = isset($config['password']) && $config['password'] !== ''
            ? ['PGPASSWORD' => $config['password']]
            : [];

        $this->executeCommandFromFile(
            ['psql', '-h', $host, '-p', $port, '-U', $user, '-d', $db],
            $path,
            $env
        );
    }
}
