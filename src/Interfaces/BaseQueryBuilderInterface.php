<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\Pagination\CursorPaginatorInterface;
use Hibla\QueryBuilder\Interfaces\Pagination\PaginatorInterface;
use Hibla\Sql\RowStream;
use Rcalicdan\QueryBuilderPrimitives\Interfaces\QueryBuilderPrimitiveInterface;

/**
 * The pure contract for asynchronous query building and execution.
 */
interface BaseQueryBuilderInterface extends QueryBuilderPrimitiveInterface, RawQueryInterface
{
    /**
     * Set the query to return results as objects instead of arrays.
     */
    public function toObject(): static;

    /**
     * Set the query to return results as associative arrays instead of objects.
     */
    public function toArray(): static;

    /**
     * Explicitly binds this existing query builder (and its AST) to a transaction.
     * Returns a cloned builder that will execute on the provided transaction.
     *
     * @param TransactionalQueryBuilderInterface $trx The active transaction builder.
     *
     * @return static
     */
    public function transacting(TransactionalQueryBuilderInterface $trx): static;

    /**
     * Execute the query and return all results.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>|array<int, \stdClass>>
     */
    public function get(): PromiseInterface;

    /**
     * Get the first result from the query.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass|null>
     */
    public function first(): PromiseInterface;

    /**
     * Get the first result or throw an exception if no record matches.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass> Rejects with RecordNotFoundException
     *                                                          when no record matches the query conditions.
     */
    public function firstOrFail(): PromiseInterface;

    /**
     * Find a record by ID.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass|null>
     */
    public function find(mixed $id, string $column = 'id'): PromiseInterface;

    /**
     * Find a record by ID or throw an exception if not found.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass> Rejects with RecordNotFoundException                                                   when no record is found for the given ID.
     */
    public function findOrFail(mixed $id, string $column = 'id'): PromiseInterface;

    /**
     * Get a single value from the first result.
     *
     * @return PromiseInterface<mixed>
     */
    public function value(string $column): PromiseInterface;

    /**
     * Retrieve an array of values from a single column, optionally keyed by another column.
     *
     * @param string $column The value column.
     * @param string|null $key Optional key column.
     *
     * @return PromiseInterface<array<array-key, mixed>>
     */
    public function pluck(string $column, ?string $key = null): PromiseInterface;

    /**
     * Count the number of records.
     *
     * @return PromiseInterface<int>
     */
    public function count(string $column = '*'): PromiseInterface;

    /**
     * Get the sum of the values of a given column.
     *
     * @param string $column
     *
     * @return PromiseInterface<mixed>
     */
    public function sum(string $column): PromiseInterface;

    /**
     * Get the average of the values of a given column.
     *
     * @param string $column
     *
     * @return PromiseInterface<mixed>
     */
    public function avg(string $column): PromiseInterface;

    /**
     * Get the minimum value of a given column.
     *
     * @param string $column
     *
     * @return PromiseInterface<mixed>
     */
    public function min(string $column): PromiseInterface;

    /**
     * Get the maximum value of a given column.
     *
     * @param string $column
     *
     * @return PromiseInterface<mixed>
     */
    public function max(string $column): PromiseInterface;

    /**
     * Check if any records exist.
     *
     * @return PromiseInterface<bool>
     */
    public function exists(): PromiseInterface;

    /**
     * Check if no records exist.
     *
     * @return PromiseInterface<bool>
     */
    public function doesntExist(): PromiseInterface;

    /**
     * Increment a column's value by a given amount.
     *
     * @param string $column
     * @param int|float $amount
     * @param array<string, mixed> $extra Extra columns to update.
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): PromiseInterface;

    /**
     * Decrement a column's value by a given amount.
     *
     * @param string $column
     * @param int|float $amount
     * @param array<string, mixed> $extra Extra columns to update.
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): PromiseInterface;

    /**
     * Insert a single record.
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function insert(array $data): PromiseInterface;

    /**
     * Insert a single record and return the inserted primary key ID.
     *
     * By default, this method retrieves the value of the 'id' column. If your database table
     * uses a different primary key column name (particularly important for PostgreSQL sequences
     * where the sequence name differs from the default), you can override this behavior by
     * passing your custom column name as the second parameter.
     *
     * @param array<string, mixed> $data The column-value pairs to insert.
     * @param string $sequence The primary key or sequence column name (defaults to 'id').
     *
     * @return PromiseInterface<int> A promise resolving to the inserted record's primary key ID.
     */
    public function insertGetId(array $data, string $sequence = 'id'): PromiseInterface;

    /**
     * Insert multiple records in a batch operation.
     *
     * @param array<array<string, mixed>> $data
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function insertBatch(array $data): PromiseInterface;

    /**
     * Insert a single record and ignore duplicate key constraints.
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function insertIgnore(array $data): PromiseInterface;

    /**
     * Insert multiple records in a batch and ignore duplicate key constraints.
     *
     * @param array<array<string, mixed>> $data
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function insertIgnoreBatch(array $data): PromiseInterface;

    /**
     * Update records matching the query conditions.
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function update(array $data): PromiseInterface;

    /**
     * Delete records matching the query conditions.
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function delete(): PromiseInterface;

    /**
     * Insert a single record or update it if a unique constraint is violated.
     *
     * @param array<string, mixed> $data
     * @param string|array<int, string> $uniqueColumns Column(s) that determine uniqueness.
     * @param array<int, string>|null $updateColumns Columns to update on conflict (null = all except unique).
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function upsert(array $data, string|array $uniqueColumns, ?array $updateColumns = null): PromiseInterface;

    /**
     * Insert multiple records or update them if a unique constraint is violated.
     *
     * @param array<int, array<string, mixed>> $data
     * @param string|array<int, string> $uniqueColumns Column(s) that determine uniqueness.
     * @param array<int, string>|null $updateColumns Columns to update on conflict (null = all except unique).
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function upsertBatch(array $data, string|array $uniqueColumns, ?array $updateColumns = null): PromiseInterface;

    /**
     * Execute the query and return an unbuffered stream of results.
     *
     * @param positive-int $bufferSize Number of rows to buffer internally per read. Defaults to 100.
     *
     * @return PromiseInterface<RowStream>
     */
    public function stream(int $bufferSize = 100): PromiseInterface;

    /**
     * Stream the query results but group them into chunk arrays before passing to the callback.
     *
     * @param positive-int $chunkSize Number of rows per chunk.
     * @param callable(array<int, array<string, mixed>|object>): (PromiseInterface<mixed>|bool|void) $callback
     *
     * @return PromiseInterface<void> Resolves when the entire stream has finished processing.
     */
    public function chunkStream(int $chunkSize, callable $callback): PromiseInterface;

    /**
     * Chunk the results of the query using LIMIT and OFFSET.
     *
     * @param positive-int $count The number of records per chunk.
     * @param callable(array<int, array<string, mixed>|object>): (PromiseInterface<mixed>|bool|void) $callback
     *
     * @return PromiseInterface<void>
     */
    public function chunk(int $count, callable $callback): PromiseInterface;

    /**
     * Chunk the results of the query by comparing IDs (avoids OFFSET penalty).
     *
     * @param positive-int $count The number of records per chunk.
     * @param callable(array<int, array<string, mixed>|object>): (PromiseInterface<mixed>|bool|void) $callback
     * @param string $column The column to chunk by (usually 'id').
     * @param string|null $alias The alias of the column if using JOINs.
     *
     * @return PromiseInterface<void>
     */
    public function chunkById(int $count, callable $callback, string $column = 'id', ?string $alias = null): PromiseInterface;

    /**
     * Stream the query results and execute a callback for each record.
     *
     * @param callable(array<string, mixed>|object $row): (PromiseInterface<mixed>|bool|void) $callback The callback executed for each record.
     * @param positive-int $bufferSize Number of rows to buffer internally per read. Defaults to 100.
     *
     * @return PromiseInterface<void> Resolves when the entire stream has finished processing.
     */
    public function each(callable $callback, int $bufferSize = 100): PromiseInterface;

    /**
     * Paginate the results with automatic request handling.
     *
     * @return PromiseInterface<PaginatorInterface>
     */
    public function paginate(int $perPage = 15, ?string $path = null): PromiseInterface;

    /**
     * Paginate with cursor-based pagination.
     *
     * @param int $perPage
     * @param string|array<int|string, string> $cursorColumns Columns to paginate by (e.g. ['score' => 'desc', 'id' => 'asc'])
     * @param string|null $path
     *
     * @return PromiseInterface<CursorPaginatorInterface>
     */
    public function cursorPaginate(int $perPage = 15, string|array $cursorColumns = 'id', ?string $path = null): PromiseInterface;
}
