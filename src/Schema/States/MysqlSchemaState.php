<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

use Hibla\QueryBuilder\Traits\ExtractsConfigValues;

class MySQLSchemaState extends SchemaState
{
    use ExtractsConfigValues;

    /**
     * Dump the MySQL schema and migrations table data to a file.
     *
     * Runs two sequential `mysqldump` commands:
     *   1. `--no-data`        — writes the full DDL to {@see $path}.
     *   2. `--no-create-info` — appends the rows of the migrations table to {@see $path}.
     *
     * @param array<string, mixed> $config Database connection configuration.
     * @param string $path Destination file path for the dump.
     * @param string $migrationsTable Name of the migrations tracking table.
     */
    public function dump(array $config, string $path, string $migrationsTable): void
    {
        $host = $this->extractString($config, 'host', '127.0.0.1');
        $port = $this->extractPort($config, '3306');
        $user = $this->extractString($config, 'username', 'root');
        $db = $this->extractString($config, 'database', '');
        $env = $this->extractPasswordEnv($config, 'MYSQL_PWD');

        $this->executeCommandAndWriteToFile(
            ['mysqldump', '-u', $user, '-h', $host, '-P', $port, '--no-data', '--skip-comments', '--skip-routines', '--no-tablespaces', $db],
            $path,
            false,
            $env
        );

        $this->executeCommandAndWriteToFile(
            ['mysqldump', '-u', $user, '-h', $host, '-P', $port, '--no-create-info', '--skip-comments', '--no-tablespaces', $db, $migrationsTable],
            $path,
            true,
            $env
        );
    }

    /**
     * Load a previously dumped SQL file into the MySQL database.
     *
     * Pipes the contents of {@see $path} directly into `mysql` via stdin.
     * If the `mysql` executable is not installed, it falls back to native PHP execution.
     *
     * @param array<string, mixed> $config Database connection configuration.
     * @param string $path Path to the SQL dump file to load.
     */
    public function load(array $config, string $path): void
    {
        $host = $this->extractString($config, 'host', '127.0.0.1');
        $port = $this->extractPort($config, '3306');
        $user = $this->extractString($config, 'username', 'root');
        $db = $this->extractString($config, 'database', '');
        $env = $this->extractPasswordEnv($config, 'MYSQL_PWD');

        try {
            $this->executeCommandFromFile(
                ['mysql', '-u', $user, '-h', $host, '-P', $port, $db],
                $path,
                $env
            );
        } catch (\RuntimeException $e) {
            $this->loadViaPhpFallback($config, $path);
        }
    }
}
