<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

class SQLiteSchemaState extends SchemaState
{
    public function dump(array $config, string $path, string $migrationsTable): void
    {
        $db = $config['database'] ?? '';

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

    public function load(array $config, string $path): void
    {
        $db = $config['database'] ?? '';

        $this->executeCommandFromFile(
            ['sqlite3', $db],
            $path
        );
    }
}
