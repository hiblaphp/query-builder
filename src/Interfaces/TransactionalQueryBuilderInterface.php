<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Sql\Transaction;

/**
 * A specialized Query Builder that operates within an active transaction.
 *
 * Provides manual transaction controls (commit, rollback, savepoints) while
 * retaining all standard query building capabilities, including nested transactions.
 */
interface TransactionalQueryBuilderInterface extends QueryBuilderInterface
{
    /**
     * Commits the transaction, making all changes permanent.
     *
     * @return PromiseInterface<void>
     */
    public function commit(): PromiseInterface;

    /**
     * Rolls back the transaction, discarding all changes.
     *
     * @return PromiseInterface<void>
     */
    public function rollback(): PromiseInterface;

    /**
     * Creates a named savepoint within the transaction.
     *
     * @param string $identifier The name of the savepoint.
     *
     * @return PromiseInterface<void>
     */
    public function savepoint(string $identifier): PromiseInterface;

    /**
     * Rolls back the transaction to a previously created named savepoint.
     *
     * @param string $identifier The name of the savepoint to roll back to.
     *
     * @return PromiseInterface<void>
     */
    public function rollbackTo(string $identifier): PromiseInterface;

    /**
     * Registers a callback to be executed only if the transaction is successfully committed.
     *
     * @param callable(): void $callback
     */
    public function onCommit(callable $callback): void;

    /**
     * Registers a callback to be executed only if the transaction is rolled back.
     *
     * @param callable(): void $callback
     */
    public function onRollback(callable $callback): void;

    /**
     * Get the underlying raw transaction instance.
     */
    public function getTransaction(): Transaction;
}
