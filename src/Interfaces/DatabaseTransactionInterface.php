<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Sql\Transaction;

interface DatabaseTransactionInterface
{
    /**
     * Start a new query builder instance for the given table.
     * Ensures locks and modifications execute safely within this transaction.
     */
    public function table(string $table): QueryBuilderInterface;

    /**
     * Execute a raw query within the transaction.
     *
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE) within the transaction.
     *
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Commit the active transaction.
     *
     * @return PromiseInterface<void>
     */
    public function commit(): PromiseInterface;

    /**
     * Rollback the active transaction.
     *
     * @return PromiseInterface<void>
     */
    public function rollback(): PromiseInterface;

    /**
     * Create a named savepoint.
     *
     * @return PromiseInterface<void>
     */
    public function savepoint(string $identifier): PromiseInterface;

    /**
     * Rollback to a named savepoint.
     *
     * @return PromiseInterface<void>
     */
    public function rollbackTo(string $identifier): PromiseInterface;

    /**
     * Register a callback to execute if the transaction commits successfully.
     */
    public function onCommit(callable $callback): void;

    /**
     * Register a callback to execute if the transaction rolls back.
     */
    public function onRollback(callable $callback): void;

    /**
     * Get the underlying raw transaction instance.
     */
    public function getTransaction(): Transaction;
}
