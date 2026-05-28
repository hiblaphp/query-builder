<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Internals;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\Exceptions\QueryBuilderException;
use Hibla\QueryBuilder\Interfaces\TransactionalQueryBuilderInterface;
use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\IsolationLevelInterface;
use Hibla\Sql\Transaction;
use Hibla\Sql\TransactionOptions;

/**
 * @internal Do not use this directly type hinting. Use TransactionalQueryBuilderInterface.
 *
 * A specialized Query Builder that operates within an active transaction.
 *
 * Provides manual transaction controls (commit, rollback, savepoints) while
 * retaining all standard query building capabilities.
 */
class TransactionalQueryBuilder extends QueryBuilder implements TransactionalQueryBuilderInterface
{
    public function __construct(
        private ?Transaction $transactionClient = null,
        ?DatabaseDriver $driver = null
    ) {
        parent::__construct($transactionClient, $driver);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface
    {
        return Promise::rejected(
            new QueryBuilderException('Cannot begin a transaction. You are already inside an active transaction. Use savepoints manually.')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface
    {
        return Promise::rejected(
            new QueryBuilderException('Nested auto-transactions are not supported on the transactional builder. Use savepoints manually.')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): PromiseInterface
    {
        return $this->getTransaction()->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): PromiseInterface
    {
        return $this->getTransaction()->rollback();
    }

    /**
     * {@inheritdoc}
     */
    public function savepoint(string $identifier): PromiseInterface
    {
        return $this->getTransaction()->savepoint($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTo(string $identifier): PromiseInterface
    {
        return $this->getTransaction()->rollbackTo($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function onCommit(callable $callback): void
    {
        $this->getTransaction()->onCommit($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function onRollback(callable $callback): void
    {
        $this->getTransaction()->onRollback($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransaction(): Transaction
    {
        $client = $this->transactionClient;

        if ($client === null) {
            throw new QueryBuilderException(
                'TransactionalQueryBuilder was not initialized with an active transaction client.'
            );
        }

        return $client;
    }
}
