<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\Sql\Result;
use Hibla\Sql\Transaction;

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
        return new QueryBuilder($this->transaction, $this->driverName, $table);
    }

    /**
     * {@inheritdoc}
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->transaction->query($sql, $bindings)
            ->then(fn (Result $result) => $result->fetchAll())
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->transaction->execute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): PromiseInterface
    {
        return $this->transaction->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): PromiseInterface
    {
        return $this->transaction->rollback();
    }

    /**
     * {@inheritdoc}
     */
    public function savepoint(string $identifier): PromiseInterface
    {
        return $this->transaction->savepoint($identifier);
    }

    /**
     * {@inheritdoc}
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
