<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;

/**
 * Base migration class that provides helper methods and configuration for migrations.
 */
abstract class Migration
{
    /**
     * The database connection to use for this migration.
     * If null, uses the default connection.
     */
    protected ?string $connection = null;

    /**
     * Indicates if the migration should be wrapped in a transaction.
     */
    protected bool $withinTransaction = true;

    /**
     * The active database transaction instance, if running within one.
     */
    protected ?DatabaseTransactionInterface $transaction = null;

    /**
     * The schema builder instance.
     */
    protected ?SchemaBuilder $schema = null;

    /**
     * Run the migration.
     */
    abstract public function up(): void;

    /**
     * Reverse the migration.
     */
    abstract public function down(): void;

    /**
     * Get the database connection for this migration.
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the database connection for this migration.
     */
    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Check if the migration should run within a transaction.
     */
    public function shouldRunWithinTransaction(): bool
    {
        return $this->withinTransaction;
    }

    /**
     * Set whether the migration should run within a transaction.
     */
    public function withoutTransaction(): self
    {
        $this->withinTransaction = false;

        return $this;
    }

    /**
     * Set the active transaction for this migration.
     */
    public function setTransaction(?DatabaseTransactionInterface $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    /**
     * Get the schema builder for the configured connection.
     */
    protected function getSchema(): SchemaBuilder
    {
        if ($this->schema === null) {
            $this->schema = new SchemaBuilder(null, $this->connection);

            if ($this->transaction !== null) {
                $this->schema->setTransaction($this->transaction);
            }
        }

        return $this->schema;
    }

    /**
     * Create a new table on the schema.
     *
     * @return PromiseInterface<int|null>
     */
    protected function create(string $table, callable $callback): PromiseInterface
    {
        return $this->getSchema()->create($table, $callback);
    }

    /**
     * Modify a table on the schema.
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    protected function table(string $table, callable $callback): PromiseInterface
    {
        return $this->getSchema()->table($table, $callback);
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @return PromiseInterface<int>
     */
    protected function dropIfExists(string $table): PromiseInterface
    {
        return $this->getSchema()->dropIfExists($table);
    }

    /**
     * Drop a table from the schema.
     *
     * @return PromiseInterface<int>
     */
    protected function drop(string $table): PromiseInterface
    {
        return $this->getSchema()->drop($table);
    }

    /**
     * Rename a table on the schema.
     *
     * @return PromiseInterface<int>
     */
    protected function rename(string $from, string $to): PromiseInterface
    {
        return $this->getSchema()->rename($from, $to);
    }

    /**
     * Determine if the given table exists.
     *
     * @return PromiseInterface<mixed>
     */
    protected function hasTable(string $table): PromiseInterface
    {
        return $this->getSchema()->hasTable($table);
    }

    /**
     * Drop a column from a table.
     *
     * @param string|list<string> $columns
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    protected function dropColumn(string $table, string|array $columns): PromiseInterface
    {
        return $this->getSchema()->dropColumn($table, $columns);
    }

    /**
     * Rename a column on a table.
     *
     * @return PromiseInterface<int|list<int>>
     */
    protected function renameColumn(string $table, string $from, string $to): PromiseInterface
    {
        return $this->getSchema()->renameColumn($table, $from, $to);
    }

    /**
     * Drop an index from a table.
     *
     * @param string|list<string> $index
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    protected function dropIndex(string $table, string|array $index): PromiseInterface
    {
        return $this->getSchema()->dropIndex($table, $index);
    }

    /**
     * Drop a foreign key from a table.
     *
     * @param string|list<string> $foreignKey
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    protected function dropForeign(string $table, string|array $foreignKey): PromiseInterface
    {
        return $this->getSchema()->dropForeign($table, $foreignKey);
    }

    /**
     * Execute raw SQL.
     *
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    protected function raw(string $sql, array $bindings = []): PromiseInterface
    {
        $client = $this->transaction ?? DB::connection($this->connection);

        /** @var PromiseInterface<array<int, array<string, mixed>>> $result */
        $result = $client->raw($sql, $bindings);

        return $result;
    }

    /**
     * Execute a raw statement.
     *
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<int>
     */
    protected function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        $client = $this->transaction ?? DB::connection($this->connection);

        return $client->rawExecute($sql, $bindings);
    }

    /**
     * Get a query builder for a table.
     *
     * @return QueryBuilderInterface
     */
    protected function db(string $table): QueryBuilderInterface
    {
        if ($this->transaction !== null) {
            return $this->transaction->table($table);
        }

        return DB::connection($this->connection)->table($table);
    }
}
