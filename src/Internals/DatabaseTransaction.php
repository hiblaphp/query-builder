<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Internals;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\Transaction;

/**
 * @internal Do not use this directly
 */
class DatabaseTransaction implements DatabaseTransactionInterface
{
    public function __construct(
        private readonly Transaction $transaction,
        private readonly string $driverName = 'mysql'
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function table(string $table): QueryBuilderInterface
    {
        return new QueryBuilder($this->transaction, $this->driverName)->from($table);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->transaction, $this->driverName)->raw($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->transaction, $this->driverName)->rawFirst($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->transaction, $this->driverName)->rawValue($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->transaction, $this->driverName)->rawExecute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function commit(): PromiseInterface
    {
        return $this->transaction->commit();
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function rollback(): PromiseInterface
    {
        return $this->transaction->rollback();
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function savepoint(string $identifier): PromiseInterface
    {
        return $this->transaction->savepoint($identifier);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<mixed>
     */
    public function rollbackTo(string $identifier): PromiseInterface
    {
        return $this->transaction->rollbackTo($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function onCommit(callable $callback): void
    {
        $this->transaction->onCommit($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function onRollback(callable $callback): void
    {
        $this->transaction->onRollback($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }
}
