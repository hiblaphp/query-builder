<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Sql\Transaction;

interface DatabaseTransactionInterface extends RawQueryInterface
{
    /**
     * Start a new query builder instance for the given table.
     * Ensures locks and modifications execute safely within this transaction.
     */
    public function table(string $table): QueryBuilderInterface;

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
