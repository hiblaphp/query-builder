<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
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
     * @return static
     */
    public function transacting(TransactionalQueryBuilderInterface $trx): static;

    /**
     * Execute the query and return all results.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>|array<int, object>>
     */
    public function get(): PromiseInterface;

    /**
     * Get the first result from the query.
     *
     * @return PromiseInterface<array<string, mixed>|object|null>
     */
    public function first(): PromiseInterface;

    /**
     * Find a record by ID.
     *
     * @return PromiseInterface<array<string, mixed>|object|null>
     */
    public function find(mixed $id, string $column = 'id'): PromiseInterface;

    /**
     * Find a record by ID or throw an exception if not found.
     *
     * @return PromiseInterface<array<string, mixed>|object>
     *
     * @throws \RuntimeException When no record is found.
     */
    public function findOrFail(mixed $id, string $column = 'id'): PromiseInterface;

    /**
     * Get a single value from the first result.
     *
     * @return PromiseInterface<mixed>
     */
    public function value(string $column): PromiseInterface;

    /**
     * Count the number of records.
     *
     * @return PromiseInterface<int>
     */
    public function count(string $column = '*'): PromiseInterface;

    /**
     * Check if any records exist.
     *
     * @return PromiseInterface<bool>
     */
    public function exists(): PromiseInterface;

    /**
     * Insert a single record.
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function insert(array $data): PromiseInterface;

    /**
     * Insert a single record and return the inserted ID.
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int> The last insert ID.
     */
    public function insertGetId(array $data): PromiseInterface;

    /**
     * Insert multiple records in a batch operation.
     *
     * @param array<array<string, mixed>> $data
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function insertBatch(array $data): PromiseInterface;

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
     * Paginate the results with automatic request handling.
     *
     * @return PromiseInterface<Paginator>
     */
    public function paginate(int $perPage = 15, ?string $path = null): PromiseInterface;

    /**
     * Paginate with cursor-based pagination.
     *
     * @return PromiseInterface<CursorPaginator>
     */
    public function cursorPaginate(int $perPage = 15, string $cursorColumn = 'id', ?string $path = null): PromiseInterface;
}