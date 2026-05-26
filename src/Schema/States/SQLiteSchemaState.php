<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

use Hibla\QueryBuilder\Traits\ExtractsConfigValues;

class SQLiteSchemaState extends SchemaState
{
    use ExtractsConfigValues;

    /**
     * Dump the SQLite schema and the contents of the migrations table to a file.
     *
     * Runs two sequential `sqlite3` commands:
     *   1. `.schema`  — writes the full DDL to {@see $path}.
     *   2. `SELECT *` — appends all rows from the migrations table as INSERT statements.
     *
     * @param array<string, mixed> $config Database connection configuration.
     * @param string $path Destination file path for the dump.
     * @param string $migrationsTable Name of the migrations tracking table.
     */
    public function dump(array $config, string $path, string $migrationsTable): void
    {
        $db = $this->extractString($config, 'database', '');

        $this->executeCommandAndWriteToFile(
            ['sqlite3', $db, '.schema'],
            $path,
            false
        );

        $this->executeCommandAndWriteToFile(
            ['sqlite3', $db, ".mode insert {$migrationsTable}", "SELECT * FROM {$migrationsTable};"],
            $path,
            true
        );
    }

    /**
     * Load a previously dumped SQL file into the SQLite database.
     *
     * Pipes the contents of {@see $path} directly into `sqlite3` via stdin.
     * If the `sqlite3` executable is not installed, it falls back to native PHP execution.
     *
     * @param array<string, mixed> $config Database connection configuration.
     * @param string $path Path to the SQL dump file to load.
     */
    public function load(array $config, string $path): void
    {
        $db = $this->extractString($config, 'database', '');

        try {
            $this->executeCommandFromFile(
                ['sqlite3', $db],
                $path
            );
        } catch (\RuntimeException $e) {
            $this->loadViaPhpFallback($config, $path);
        }
    }
}
