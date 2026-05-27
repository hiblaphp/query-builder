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
interface ConnectionQueryBuilderInterface extends QueryBuilderInterface
{
    /**
     * Execute a callback within an automatically managed transaction.
     *
     * @template TResult
     * @param callable(TransactionalQueryBuilderInterface): TResult $callback
     * @param TransactionOptions|null $options
     * @return PromiseInterface<TResult>
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface;

    /**
     * Manually begin a transaction.
     *
     * @param IsolationLevelInterface|null $isolationLevel
     * @return PromiseInterface<TransactionalQueryBuilderInterface>
     */
    public function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface;
}