<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Internals;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\IsolationLevelInterface;
use Hibla\Sql\SqlClientInterface;
use Hibla\Sql\Transaction;
use Hibla\Sql\TransactionOptions;

/**
 * @internal Do not use this directly
 */
class DatabaseConnection implements DatabaseConnectionInterface
{
    public function __construct(
        private readonly SqlClientInterface $client,
        private readonly string $driverName = 'mysql'
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function table(string $table): QueryBuilderInterface
    {
        return new QueryBuilder($this->client, $this->getDriverEnum())->from($table);
    }

    /**
     * {@inheritdoc}
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->getDriverEnum())->raw($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function rawStream(string $sql, array $bindings = [], int $bufferSize = 100): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->getDriverEnum())->rawStream($sql, $bindings, $bufferSize);
    }

    /**
     * {@inheritdoc}
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->getDriverEnum())->rawFirst($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->getDriverEnum())->rawValue($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->getDriverEnum())->rawExecute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface
    {
        return $this->client->transaction(function (Transaction $rawTx) use ($callback) {
            $txBuilder = new TransactionalQueryBuilder($rawTx, $this->getDriverEnum());

            return $callback($txBuilder);
        }, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface
    {
        $promise = $this->client->beginTransaction($isolationLevel)->then(
            fn (Transaction $rawTx) => new TransactionalQueryBuilder($rawTx, $this->getDriverEnum())
        );

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): SqlClientInterface
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return $this->driverName;
    }

    /**
     * Get the current driver as an Enum for strict constructor passing.
     */
    private function getDriverEnum(): DatabaseDriver
    {
        return DatabaseDriver::tryFrom($this->driverName) ?? DatabaseDriver::Mysql;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        $this->client->close();
    }

    /**
     * {@inheritdoc}
     */
    public function closeAsync(): PromiseInterface
    {
        return $this->client->closeAsync();
    }
}
