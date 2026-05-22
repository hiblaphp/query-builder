<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
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
        return new QueryBuilder($this->client, $this->driverName, $table);
    }

    /**
     * {@inheritdoc}
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->driverName)->raw($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->driverName)->rawFirst($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->driverName)->rawValue($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return new QueryBuilder($this->client, $this->driverName)->rawExecute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface
    {
        return $this->client->transaction(function (Transaction $rawTx) use ($callback) {
            $wrappedTx = new DatabaseTransaction($rawTx, $this->driverName);

            return $callback($wrappedTx);
        }, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): PromiseInterface
    {
        return $this->client->beginTransaction()->then(
            fn (Transaction $rawTx) => new DatabaseTransaction($rawTx, $this->driverName)
        );
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
}
