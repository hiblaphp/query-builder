<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;

class MigrationRepository
{
    private string $table;

    private string $driver;

    private ?string $connection = null;

    /**
     * Create a new migration repository instance.
     *
     * @param string $table The name of the migrations table.
     * @param string|null $connection The database connection to use.
     */
    public function __construct(string $table = 'migrations', ?string $connection = null)
    {
        $this->table = $table;
        $this->connection = $connection;
        $this->driver = $this->getConnection()->getDriverName();
    }

    /**
     * Get the database connection to use.
     */
    private function getConnection(): DatabaseConnectionInterface
    {
        return DB::connection($this->connection);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return match ($this->driver) {
            'pgsql', 'pgsql_native' => "\"{$identifier}\"",
            'sqlite', 'mysql', 'mysqli' => "`{$identifier}`",
            default => "`{$identifier}`",
        };
    }

    /**
     * Create the migration repository data store.
     *
     * @return PromiseInterface<int> Resolves with the number of affected rows.
     */
    public function createRepository(): PromiseInterface
    {
        $table = $this->quoteIdentifier($this->table);

        $sql = match ($this->driver) {
            'pgsql', 'pgsql_native' => "CREATE TABLE IF NOT EXISTS {$table} (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            'sqlite' => "CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL,
                executed_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            default => "CREATE TABLE IF NOT EXISTS {$table} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        };

        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Get the list of completed migrations.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>> Resolves with a list of migration records.
     */
    public function getRan(): PromiseInterface
    {
        $table = $this->quoteIdentifier($this->table);

        /** @var PromiseInterface<array<int, array<string, mixed>>> $result */
        $result = $this->getConnection()
            ->table($this->table)
            ->toArray()
            ->raw(
                "SELECT id, migration, batch, executed_at FROM {$table} ORDER BY batch DESC, id DESC",
                []
            )
        ;

        return $result;
    }

    /**
     * Get the list of migrations that were part of the last batch.
     *
     * @param int $steps The number of batches to roll back.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>> Resolves with a list of migration records.
     */
    public function getMigrations(int $steps): PromiseInterface
    {
        $table = $this->quoteIdentifier($this->table);

        $sql = "SELECT migration FROM {$table} ORDER BY batch DESC, migration DESC LIMIT ?";

        /** @var PromiseInterface<array<int, array<string, mixed>>> $result */
        $result = $this->getConnection()
            ->table($this->table)
            ->toArray()
            ->raw($sql, [$steps])
        ;

        return $result;
    }

    /**
     * Get the last migration batch.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>> Resolves with a list of migration records.
     */
    public function getLast(): PromiseInterface
    {
        $table = $this->quoteIdentifier($this->table);

        /** @var PromiseInterface<array<int, array<string, mixed>>> $result */
        $result = $this->getConnection()
            ->table($this->table)
            ->toArray()
            ->raw(
                "SELECT id, migration, batch, executed_at FROM {$table} WHERE batch = (SELECT MAX(batch) FROM {$table}) ORDER BY id DESC",
                []
            )
        ;

        return $result;
    }

    /**
     * Log that a migration was run.
     *
     * @param string $file The migration file name.
     * @param int $batch The batch number.
     * @param DatabaseTransactionInterface|null $tx The active transaction, if any.
     *
     * @return PromiseInterface<int> Resolves with the number of affected rows.
     */
    public function log(string $file, int $batch, ?DatabaseTransactionInterface $tx = null): PromiseInterface
    {
        $table = $this->quoteIdentifier($this->table);
        $client = $tx ?? $this->getConnection();

        return $client->rawExecute(
            "INSERT INTO {$table} (migration, batch) VALUES (?, ?)",
            [$file, $batch]
        );
    }

    /**
     * Remove a migration from the log.
     *
     * @param string $migration The migration file name.
     * @param DatabaseTransactionInterface|null $tx The active transaction, if any.
     *
     * @return PromiseInterface<int> Resolves with the number of affected rows.
     */
    public function delete(string $migration, ?DatabaseTransactionInterface $tx = null): PromiseInterface
    {
        $table = $this->quoteIdentifier($this->table);
        $client = $tx ?? $this->getConnection();

        return $client->rawExecute(
            "DELETE FROM {$table} WHERE migration = ?",
            [$migration]
        );
    }

    /**
     * Get the next migration batch number.
     *
     * @return PromiseInterface<mixed> Resolves with the next batch number (int) or null.
     */
    public function getNextBatchNumber(): PromiseInterface
    {
        $table = $this->quoteIdentifier($this->table);

        return $this->getConnection()->rawValue(
            "SELECT MAX(batch) FROM {$table}",
            []
        );
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return PromiseInterface<mixed> Resolves with the count (int) of matching tables.
     */
    public function repositoryExists(): PromiseInterface
    {
        $sql = match ($this->driver) {
            'pgsql', 'pgsql_native' => "SELECT COUNT(*) FROM information_schema.tables 
                       WHERE table_schema = 'public' AND table_name = ?",
            'sqlite' => "SELECT COUNT(*) FROM sqlite_master 
                        WHERE type='table' AND name=?",
            default => 'SELECT COUNT(*) FROM information_schema.tables 
                       WHERE table_schema = DATABASE() AND table_name = ?',
        };

        return $this->getConnection()->rawValue($sql, [$this->table]);
    }
}
