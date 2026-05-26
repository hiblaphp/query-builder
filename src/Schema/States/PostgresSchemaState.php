<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

use Hibla\QueryBuilder\Traits\ExtractsConfigValues;

class PostgresSchemaState extends SchemaState
{
    use ExtractsConfigValues;

    /**
     * Dump the PostgreSQL schema and migrations table data to a file.
     *
     * Runs two sequential `pg_dump` commands:
     *   1. `--schema-only` — writes the full DDL (no ownership/privileges) to {@see $path}.
     *   2. `--data-only`   — appends the rows of the migrations table to {@see $path}.
     *
     * @param array<string, mixed> $config Database connection configuration.
     * @param string $path Destination file path for the dump.
     * @param string $migrationsTable Name of the migrations tracking table.
     */
    public function dump(array $config, string $path, string $migrationsTable): void
    {
        $host = $this->extractString($config, 'host', '127.0.0.1');
        $port = $this->extractPort($config, '5432');
        $user = $this->extractString($config, 'username', 'postgres');
        $db = $this->extractString($config, 'database', '');
        $env = $this->extractPasswordEnv($config, 'PGPASSWORD');

        $this->executeCommandAndWriteToFile(
            ['pg_dump', '-h', $host, '-p', $port, '-U', $user, '--schema-only', '--no-owner', '--no-privileges', $db],
            $path,
            false,
            $env
        );

        $this->executeCommandAndWriteToFile(
            ['pg_dump', '-h', $host, '-p', $port, '-U', $user, '--data-only', '-t', $migrationsTable, $db],
            $path,
            true,
            $env
        );
    }

    /**
     * Load a previously dumped SQL file into the PostgreSQL database.
     *
     * Pipes the contents of {@see $path} directly into `psql` via stdin.
     * If the `psql` executable is not installed, it falls back to native PHP execution.
     *
     * @param array<string, mixed> $config Database connection configuration.
     * @param string $path Path to the SQL dump file to load.
     */
    public function load(array $config, string $path): void
    {
        $host = $this->extractString($config, 'host', '127.0.0.1');
        $port = $this->extractPort($config, '5432');
        $user = $this->extractString($config, 'username', 'postgres');
        $db = $this->extractString($config, 'database', '');
        $env = $this->extractPasswordEnv($config, 'PGPASSWORD');

        try {
            $this->executeCommandFromFile(
                ['psql', '-h', $host, '-p', $port, '-U', $user, '-d', $db],
                $path,
                $env
            );
        } catch (\RuntimeException $e) {
            $this->loadViaPhpFallback($config, $path);
        }
    }
}
