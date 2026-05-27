<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Sql\IsolationLevelInterface;
use Hibla\Sql\TransactionOptions;

/**
 * A Query Builder that is attached to a root database connection.
 * It has the capability to initiate new transactions.
 */
interface QueryBuilderInterface extends BaseQueryBuilderInterface
{
    /**
     * Execute a callback within an automatically managed transaction.
     * The callback receives a clean, fresh TransactionalQueryBuilderInterface.
     *
     * @template TResult
     *
     * @param callable(TransactionalQueryBuilderInterface): TResult $callback
     * @param TransactionOptions|null $options
     *
     * @return PromiseInterface<TResult>
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface;

    /**
     * Manually begin a transaction.
     * Returns a clean, fresh TransactionalQueryBuilderInterface.
     *
     * @param IsolationLevelInterface|null $isolationLevel
     *
     * @return PromiseInterface<TransactionalQueryBuilderInterface>
     */
    public function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface;
}
