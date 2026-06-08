<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\Exceptions\QueryBuilderException;
use Hibla\QueryBuilder\Exceptions\RecordNotFoundException;
use Hibla\QueryBuilder\Interfaces\ConnectionResolverInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\Interfaces\TransactionalQueryBuilderInterface;
use Hibla\QueryBuilder\Internals\TransactionalQueryBuilder;
use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
use Hibla\QueryBuilder\Streams\MappedRowStream;
use Hibla\QueryBuilder\Utilities\CursorPaginationHelper;
use Hibla\QueryBuilder\Utilities\RequestHelper;
use Hibla\Sql\IsolationLevelInterface;
use Hibla\Sql\QueryInterface;
use Hibla\Sql\Result;
use Hibla\Sql\RowStream;
use Hibla\Sql\SqlClientInterface;
use Hibla\Sql\Transaction;
use Hibla\Sql\TransactionOptions;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

use function Hibla\async;
use function Hibla\await;

class QueryBuilder extends QueryBuilderBase implements QueryBuilderInterface
{
    private QueryInterface $client;

    private bool $returnAsObject = true;

    private static ?ConnectionResolverInterface $resolver = null;

    /**
     * @internal This method is used by the DatabaseManager to set the global connection resolver bridge.
     */
    public static function setConnectionResolver(ConnectionResolverInterface $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @param QueryInterface|array<string, mixed>|string|null $connection
     */
    public function __construct(
        QueryInterface|array|string|null $connection = null,
        ?DatabaseDriver $driver = null
    ) {
        if ($connection instanceof QueryInterface) {
            $this->client = $connection;
            $this->driver = $driver !== null ? $driver->value : DatabaseDriver::Mysql->value;
        } elseif (\is_array($connection)) {
            $this->ensureResolverIsSet();
            \assert(self::$resolver !== null);
            $this->client = self::$resolver->resolveClientFromConfig($connection);

            $configDriver = \is_string($connection['driver'] ?? null) ? $connection['driver'] : DatabaseDriver::Mysql->value;
            $this->driver = $driver !== null ? $driver->value : $configDriver;
        } else {
            $this->ensureResolverIsSet();
            \assert(self::$resolver !== null);
            $conn = self::$resolver->connection($connection);
            $this->client = $conn->getClient();
            $this->driver = $driver !== null ? $driver->value : $conn->getDriverName();
        }
    }

    /**
     * Override the primitive base class method to ensure subqueries
     * (like whereExists) inherit the exact same client and driver.
     */
    protected function newQuery(): static
    {
        return new static($this->client, $this->getDriverEnum());
    }

    /**
     * {@inheritdoc}
     */
    public function toObject(): static
    {
        $clone = clone $this;
        $clone->returnAsObject = true;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): static
    {
        $clone = clone $this;
        $clone->returnAsObject = false;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function transacting(TransactionalQueryBuilderInterface $trx): static
    {
        $clone = clone $this;
        $clone->client = $trx->getTransaction();

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface
    {
        if ($this->client instanceof SqlClientInterface) {
            /** @var SqlClientInterface $sqlClient */
            $sqlClient = $this->client;

            return $sqlClient->transaction(function (Transaction $tx) use ($callback) {
                $txBuilder = new TransactionalQueryBuilder($tx, $this->getDriverEnum());

                return $callback($txBuilder);
            }, $options);
        }

        if ($this->client instanceof Transaction) {
            /** @var Transaction $transaction */
            $transaction = $this->client;

            $savepointId = 'sp_' . bin2hex(random_bytes(4));

            $innerWorkPromise = null;

            $promise = $transaction->savepoint($savepointId)->then(function () use ($callback, $savepointId, &$innerWorkPromise, $transaction) {
                $txBuilder = new TransactionalQueryBuilder($transaction, $this->getDriverEnum());

                $innerWorkPromise = async(fn () => $callback($txBuilder));

                return $innerWorkPromise->catch(function (\Throwable $e) use ($savepointId, &$innerWorkPromise, $transaction) {
                    if ($e instanceof CancelledException && ! $innerWorkPromise->isSettled()) {
                        $innerWorkPromise->cancel();
                    }

                    return $transaction->rollbackTo($savepointId)->then(fn () => throw $e);
                });
            });

            Promise::forwardCancellation($promise, $innerWorkPromise);

            return Promise::propagateCancellation($promise);
        }

        return Promise::rejected(new QueryBuilderException('The underlying client does not support transactions.'));
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface
    {
        if ($this->client instanceof SqlClientInterface) {
            $promise = $this->client->beginTransaction($isolationLevel)->then(function (Transaction $tx) {
                return new TransactionalQueryBuilder($tx, $this->getDriverEnum());
            });

            return Promise::propagateCancellation($promise);
        }

        return Promise::rejected(new QueryBuilderException('Cannot begin a transaction. Client is not a root SqlClient, or a transaction is already active.'));
    }

    /**
     * {@inheritdoc}
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        $promise = $this->client->query($sql, $bindings)
            ->then(function (Result $result) {
                $rows = $result->fetchAll();

                return $this->returnAsObject ? $this->convertToObjects($rows) : $rows;
            })
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function rawStream(string $sql, array $bindings = [], int $bufferSize = 100): PromiseInterface
    {
        $promise = $this->client->stream($sql, $bindings, $bufferSize)
            ->then(function (RowStream $stream) {
                if ($this->returnAsObject) {
                    return new MappedRowStream($stream, static fn (array $row): object => (object) $row);
                }

                return $stream;
            })
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function stream(int $bufferSize = 100): PromiseInterface
    {
        $sql = $this->buildSelectQuery();

        $promise = $this->client->stream($sql, array_values($this->getCompiledBindings()), $bufferSize)
            ->then(function (RowStream $stream) {
                if ($this->returnAsObject) {
                    return new MappedRowStream($stream, static fn (array $row): object => (object) $row);
                }

                return $stream;
            })
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        $promise = $this->client->fetchOne($sql, $bindings)
            ->then(function (?array $result) {
                if ($result === null) {
                    return null;
                }

                return $this->returnAsObject ? (object) $result : $result;
            })
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->client->fetchValue($sql, null, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->client->execute($sql, $bindings);
    }

    /**
     * Convert array results to objects if requested.
     *
     * @param array<int, array<string, mixed>> $results
     *
     * @return array<int, object>
     */
    private function convertToObjects(array $results): array
    {
        return array_map(static fn (array $row): object => (object) $row, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function get(): PromiseInterface
    {
        $sql = $this->buildSelectQuery();

        $promise = $this->client->query($sql, array_values($this->getCompiledBindings()))
            ->then(function (Result $result) {
                $rows = $result->fetchAll();

                return $this->returnAsObject ? $this->convertToObjects($rows) : $rows;
            })
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function first(): PromiseInterface
    {
        $instanceWithLimit = $this->limit(1);
        $sql = $instanceWithLimit->buildSelectQuery();

        $promise = $instanceWithLimit->client->fetchOne($sql, array_values($instanceWithLimit->getCompiledBindings()))
            ->then(function (?array $result) {
                if ($result === null) {
                    return null;
                }

                return $this->returnAsObject ? (object) $result : $result;
            })
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function firstOrFail(): PromiseInterface
    {
        $promise = $this->first()->then(function (array|object|null $result) {
            if ($result === null) {
                throw new RecordNotFoundException(
                    'No record found matching the given query conditions.'
                );
            }

            return $result;
        });

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function find(mixed $id, string $column = 'id'): PromiseInterface
    {
        $promise = $this->where($column, $id)->first();

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail(mixed $id, string $column = 'id'): PromiseInterface
    {
        $promise = $this->find($id, $column)->then(function (array|object|null $result) use ($id, $column) {
            if ($result === null) {
                $idString = \is_scalar($id) ? (string) $id : 'complex_type';

                throw new RecordNotFoundException("Record not found with {$column} = {$idString}");
            }

            return $result;
        });

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function value(string $column): PromiseInterface
    {
        $promise = $this->select($column)->first()->then(function (array|object|null $result) use ($column) {
            if ($result === null) {
                return null;
            }

            // Cast to array to avoid dynamic property access on object
            $row = \is_object($result) ? (array) $result : $result;

            return $row[$column] ?? null;
        });

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function pluck(string $column, ?string $key = null): PromiseInterface
    {
        $query = clone $this;

        if ($key === null) {
            $query = $query->select($column);
        } else {
            $query = $query->select($column, $key);
        }

        $promise = $query->get()->then(function (array $results) use ($column, $key) {
            $pluckResult = [];

            foreach ($results as $row) {
                $value = CursorPaginationHelper::extractColumnValue($row, $column);

                if ($key === null) {
                    $pluckResult[] = $value;
                } else {
                    $keyValue = CursorPaginationHelper::extractColumnValue($row, $key);

                    if (\is_scalar($keyValue) || (\is_object($keyValue) && method_exists($keyValue, '__toString'))) {
                        $pluckResult[(string) $keyValue] = $value;
                    }
                }
            }

            return $pluckResult;
        });

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $column = '*'): PromiseInterface
    {
        $sql = $this->buildCountQuery($column);

        $promise = $this->client->fetchValue($sql, null, array_values($this->getCompiledBindings()))
            ->then(fn (mixed $value) => is_numeric($value) ? (int) $value : 0)
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function sum(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('SUM', $column);

        return $this->client->fetchValue($sql, null, array_values($this->getCompiledBindings()));
    }

    /**
     * {@inheritdoc}
     */
    public function avg(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('AVG', $column);

        return $this->client->fetchValue($sql, null, array_values($this->getCompiledBindings()));
    }

    /**
     * {@inheritdoc}
     */
    public function min(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('MIN', $column);

        return $this->client->fetchValue($sql, null, array_values($this->getCompiledBindings()));
    }

    /**
     * {@inheritdoc}
     */
    public function max(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('MAX', $column);

        return $this->client->fetchValue($sql, null, array_values($this->getCompiledBindings()));
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): PromiseInterface
    {
        $sql = $this->buildExistsQuery();

        $promise = $this->client->fetchValue($sql, null, array_values($this->getCompiledBindings()))
            ->then(function (mixed $value) {
                if (\is_bool($value)) {
                    return $value;
                }

                if (\is_string($value)) {
                    $normalized = strtolower($value);
                    if ($normalized === 't' || $normalized === 'true' || $normalized === '1') {
                        return true;
                    }
                    if ($normalized === 'f' || $normalized === 'false' || $normalized === '0') {
                        return false;
                    }
                }

                if (\is_int($value)) {
                    return $value > 0;
                }

                if (\is_float($value)) {
                    return $value > 0;
                }

                return false;
            })
        ;

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function doesntExist(): PromiseInterface
    {
        $promise = $this->exists()->then(fn (bool $exists) => ! $exists);

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): PromiseInterface
    {
        $sql = $this->buildIncrementQuery($column, $amount, $extra);

        $bindings = [...array_values($extra), ...array_values($this->getCompiledBindings())];

        return $this->client->execute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): PromiseInterface
    {
        $sql = $this->buildDecrementQuery($column, $amount, $extra);

        $bindings = [...array_values($extra), ...array_values($this->getCompiledBindings())];

        return $this->client->execute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildInsertQuery($data);

        return $this->client->execute($sql, array_values($data));
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $data
     * @param string $sequence Optional sequence/primary key name for PostgreSQL (defaults to 'id')
     */
    public function insertGetId(array $data, string $sequence = 'id'): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }

        $sql = $this->buildInsertQuery($data);

        // PostgreSQL requires an explicit RETURNING clause to yield the auto-increment ID
        if ($this->getDriverEnum() === DatabaseDriver::Postgres) {
            $sql .= ' RETURNING ' . $sequence;
        }

        return $this->client->executeGetId($sql, array_values($data));
    }

    /**
     * {@inheritdoc}
     */
    public function insertBatch(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildInsertBatchQuery($data);

        $bindings = [];
        foreach ($data as $row) {
            array_push($bindings, ...array_values($row));
        }

        return $this->client->execute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildUpdateQuery($data);

        $bindings = [...array_values($data), ...array_values($this->getCompiledBindings())];

        return $this->client->execute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(): PromiseInterface
    {
        $sql = $this->buildDeleteQuery();

        return $this->client->execute($sql, array_values($this->getCompiledBindings()));
    }

    /**
     * {@inheritdoc}
     */
    public function upsert(array $data, string|array $uniqueColumns, ?array $updateColumns = null): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }

        $sql = $this->buildUpsertQuery($data, $uniqueColumns, $updateColumns);

        return $this->client->execute($sql, array_values($data));
    }

    /**
     * {@inheritdoc}
     */
    public function upsertBatch(array $data, string|array $uniqueColumns, ?array $updateColumns = null): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }

        $sql = $this->buildUpsertQuery($data, $uniqueColumns, $updateColumns);

        $bindings = [];
        foreach ($data as $row) {
            array_push($bindings, ...array_values($row));
        }

        return $this->client->execute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function each(callable $callback, int $bufferSize = 100): PromiseInterface
    {
        $innerPromise = null;

        $promise = $this->stream($bufferSize)->then(function (RowStream $stream) use ($callback, &$innerPromise) {
            $innerPromise = async(function () use ($stream, $callback) {
                try {
                    foreach ($stream as $row) {
                        $result = $callback($row);

                        if ($result instanceof PromiseInterface) {
                            $result = await($result);
                        }

                        if ($result === false) {
                            $stream->cancel();

                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $stream->cancel();

                    throw $e;
                }
            });

            $innerPromise->onCancel($stream->cancel(...));

            return $innerPromise;
        });

        Promise::forwardCancellation($promise, $innerPromise);

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function chunkStream(int $chunkSize, callable $callback): PromiseInterface
    {
        $innerPromise = null;

        $promise = $this->stream($chunkSize)->then(function (RowStream $stream) use ($chunkSize, $callback, &$innerPromise) {
            $innerPromise = async(function () use ($stream, $chunkSize, $callback) {
                $buffer = [];

                try {
                    foreach ($stream as $row) {
                        $buffer[] = $row;

                        if (\count($buffer) >= $chunkSize) {
                            $result = $callback($buffer);

                            if ($result instanceof PromiseInterface) {
                                $result = await($result);
                            }

                            $buffer = []; // clear buffer

                            if ($result === false) {
                                $stream->cancel();

                                break;
                            }
                        }
                    }

                    // Process any remaining rows that didn't exactly fill the last chunk
                    if (\count($buffer) > 0) {
                        $result = $callback($buffer);

                        if ($result instanceof PromiseInterface) {
                            await($result);
                        }
                    }
                } catch (\Throwable $e) {
                    $stream->cancel();

                    throw $e;
                }
            });

            $innerPromise->onCancel($stream->cancel(...));

            return $innerPromise;
        });

        Promise::forwardCancellation($promise, $innerPromise);

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $count, callable $callback): PromiseInterface
    {
        /** @var PromiseInterface<mixed>|null $activePromise */
        $activePromise = null;

        $innerPromise = async(function () use ($count, $callback, &$activePromise) {
            $page = 1;

            while (true) {
                $activePromise = $this->forPage($page, $count)->get();
                $results = await($activePromise);
                $activePromise = null;

                if (\count($results) === 0) {
                    break;
                }

                $callbackResult = $callback($results);

                if ($callbackResult instanceof PromiseInterface) {
                    $activePromise = $callbackResult;
                    $callbackResult = await($activePromise);
                    $activePromise = null;
                }

                if ($callbackResult === false) {
                    break;
                }

                if (\count($results) < $count) {
                    break;
                }

                $page++;
            }
        });

        $innerPromise->onCancel(function () use (&$activePromise) {
            if ($activePromise !== null && ! $activePromise->isSettled()) {
                $activePromise->cancel();
            }
        });

        return Promise::propagateCancellation($innerPromise);
    }

    /**
     * {@inheritdoc}
     */
    public function chunkById(int $count, callable $callback, string $column = 'id', ?string $alias = null): PromiseInterface
    {
        /** @var PromiseInterface<mixed>|null $activePromise */
        $activePromise = null;

        $innerPromise = async(function () use ($count, $callback, $column, $alias, &$activePromise) {
            $lastId = null;
            $extractColumn = $alias ?? $column;

            while (true) {
                $query = $this->orderByAsc($extractColumn);

                if ($lastId !== null) {
                    $query = $query->where($column, '>', $lastId);
                }

                $activePromise = $query->limit($count)->get();
                $results = await($activePromise);
                $activePromise = null;

                if (\count($results) === 0) {
                    break;
                }

                $callbackResult = $callback($results);

                if ($callbackResult instanceof PromiseInterface) {
                    $activePromise = $callbackResult;
                    $callbackResult = await($activePromise);
                    $activePromise = null;
                }

                if ($callbackResult === false) {
                    break;
                }

                if (\count($results) < $count) {
                    break;
                }

                $lastItem = end($results);
                $lastId = CursorPaginationHelper::extractColumnValue($lastItem, $extractColumn);
            }
        });

        $innerPromise->onCancel(function () use (&$activePromise) {
            if ($activePromise !== null && ! $activePromise->isSettled()) {
                $activePromise->cancel();
            }
        });

        return Promise::propagateCancellation($innerPromise);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, ?string $path = null): PromiseInterface
    {
        $page = RequestHelper::getCurrentPage();
        $path ??= RequestHelper::getCurrentPath();

        $innerPromise = null;

        $promise = $this->count()->then(function (int $total) use ($perPage, $page, $path, &$innerPromise) {
            $innerPromise = $this->forPage($page, $perPage)->get()
                ->then(function (array $items) use ($total, $perPage, $page, $path) {
                    return new Paginator($items, $total, $perPage, $page, $path);
                })
            ;

            return $innerPromise;
        });

        Promise::forwardCancellation($promise, $innerPromise);

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function cursorPaginate(int $perPage = 15, string|array $cursorColumns = 'id', ?string $path = null): PromiseInterface
    {
        $cursor = RequestHelper::getCursor();
        $path ??= RequestHelper::getCurrentPath();

        $query = CursorPaginationHelper::applyCursor($this, $cursor, $cursorColumns);

        $promise = $query->limit($perPage + 1)->get()->then(function (array $results) use ($perPage, $cursorColumns, $path) {
            $hasMore = \count($results) > $perPage;
            if ($hasMore) {
                array_pop($results);
            }

            $nextCursor = CursorPaginationHelper::resolveNextCursor($results, $cursorColumns, $hasMore);

            return new CursorPaginator($results, $perPage, $nextCursor, $cursorColumns, $path);
        });

        return Promise::propagateCancellation($promise);
    }

    private function ensureResolverIsSet(): void
    {
        if (self::$resolver === null) {
            throw new QueryBuilderException(
                'A ConnectionResolver has not been set. Either initialize the DatabaseManager first (e.g., DB::getManager()), ' .
                    'or pass a valid QueryInterface directly into the QueryBuilder constructor.'
            );
        }
    }

    /**
     * Get the current driver as an Enum for strict constructor passing.
     */
    private function getDriverEnum(): DatabaseDriver
    {
        return DatabaseDriver::tryFrom((string) $this->driver) ?? DatabaseDriver::Mysql;
    }
}
