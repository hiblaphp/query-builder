<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\Exceptions\QueryBuilderException;
use Hibla\QueryBuilder\Exceptions\RecordNotFoundException;
use Hibla\QueryBuilder\Interfaces\ConnectionResolverInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\Interfaces\TransactionalQueryBuilderInterface;
use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
use Hibla\QueryBuilder\Utilities\CursorPaginationHelper;
use Hibla\QueryBuilder\Utilities\RequestHelper;
use Hibla\Sql\IsolationLevelInterface;
use Hibla\Sql\QueryInterface;
use Hibla\Sql\Result;
use Hibla\Sql\SqlClientInterface;
use Hibla\Sql\Transaction;
use Hibla\Sql\TransactionOptions;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

use function Hibla\async;

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
        string|null $driver = null
    ) {
        if ($connection instanceof QueryInterface) {
            $this->client = $connection;
            $this->driver = $driver ?? 'mysql';
        } elseif (\is_array($connection)) {
            $this->ensureResolverIsSet();
            \assert(self::$resolver !== null);
            $this->client = self::$resolver->resolveClientFromConfig($connection);
            $this->driver = $driver ?? (\is_string($connection['driver'] ?? null) ? $connection['driver'] : 'mysql');
        } else {
            $this->ensureResolverIsSet();
            \assert(self::$resolver !== null);
            $conn = self::$resolver->connection($connection);
            $this->client = $conn->getClient();
            $this->driver = $driver ?? $conn->getDriverName();
        }
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
     * Override the primitive base class method to ensure subqueries
     * (like whereExists) inherit the exact same client and driver.
     */
    protected function newQuery(): static
    {
        return new static($this->client, $this->driver);
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
            return $this->client->transaction(function (Transaction $tx) use ($callback) {
                $txBuilder = new TransactionalQueryBuilder($tx, $this->driver);
                
                return $callback($txBuilder);
            }, $options);
        }

        // Simulate nested transactions via savepoints if already in a transaction
        if ($this->client instanceof Transaction) {
            $savepointId = 'sp_' . bin2hex(random_bytes(4));
            
            return $this->client->savepoint($savepointId)->then(function() use ($callback, $savepointId) {
                $txBuilder = new TransactionalQueryBuilder($this->client, $this->driver);
                
                return async(fn() => $callback($txBuilder))->catch(function(\Throwable $e) use ($savepointId) {
                    return $this->client->rollbackTo($savepointId)->then(fn() => throw $e);
                });
            });
        }

        throw new QueryBuilderException('The underlying client does not support transactions.');
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface
    {
        if ($this->client instanceof SqlClientInterface) {
            return $this->client->beginTransaction($isolationLevel)->then(function (Transaction $tx) {
                return new TransactionalQueryBuilder($tx, $this->driver);
            });
        }
        
        throw new QueryBuilderException('Cannot begin a transaction. Client is not a root SqlClient, or a transaction is already active.');
    }

    /**
     * {@inheritdoc}
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->client->query($sql, array_values($bindings))
            ->then(function (Result $result) {
                $rows = $result->fetchAll();

                return $this->returnAsObject ? $this->convertToObjects($rows) : $rows;
            })
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->client->fetchOne($sql, array_values($bindings))
            ->then(function (?array $result) {
                if ($result === null) {
                    return null;
                }

                return $this->returnAsObject ? (object) $result : $result;
            })
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->client->fetchValue($sql, null, array_values($bindings));
    }

    /**
     * {@inheritdoc}
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->client->execute($sql, array_values($bindings));
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
        return array_map(static fn(array $row): object => (object) $row, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function get(): PromiseInterface
    {
        $sql = $this->buildSelectQuery();

        return $this->client->query($sql, array_values($this->getCompiledBindings()))
            ->then(function (Result $result) {
                $rows = $result->fetchAll();

                return $this->returnAsObject ? $this->convertToObjects($rows) : $rows;
            })
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function first(): PromiseInterface
    {
        $instanceWithLimit = $this->limit(1);
        $sql = $instanceWithLimit->buildSelectQuery();

        return $instanceWithLimit->client->fetchOne($sql, array_values($instanceWithLimit->getCompiledBindings()))
            ->then(function (?array $result) {
                if ($result === null) {
                    return null;
                }

                return $this->returnAsObject ? (object) $result : $result;
            })
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function find(mixed $id, string $column = 'id'): PromiseInterface
    {
        return $this->where($column, $id)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail(mixed $id, string $column = 'id'): PromiseInterface
    {
        return $this->find($id, $column)->then(function (array|object|null $result) use ($id, $column) {
            if ($result === null) {
                $idString = \is_scalar($id) ? (string) $id : 'complex_type';

                throw new RecordNotFoundException("Record not found with {$column} = {$idString}");
            }

            return $result;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function value(string $column): PromiseInterface
    {
        return $this->select($column)->first()->then(function (array|object|null $result) use ($column) {
            if ($result === null) {
                return null;
            }

            // Cast to array to avoid dynamic property access on object
            $row = \is_object($result) ? (array) $result : $result;

            return $row[$column] ?? null;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $column = '*'): PromiseInterface
    {
        $sql = $this->buildCountQuery($column);

        return $this->client->fetchValue($sql, null, array_values($this->getCompiledBindings()))
            ->then(fn(mixed $value) => is_numeric($value) ? (int) $value : 0)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): PromiseInterface
    {
        return $this->count()->then(fn(int $count) => $count > 0);
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
     */
    public function insertGetId(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildInsertQuery($data);

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
    public function paginate(int $perPage = 15, ?string $path = null): PromiseInterface
    {
        $page = RequestHelper::getCurrentPage();
        $path ??= RequestHelper::getCurrentPath();

        return $this->count()->then(function (int $total) use ($perPage, $page, $path) {
            return $this->forPage($page, $perPage)->get()
                ->then(function (array $items) use ($total, $perPage, $page, $path) {
                    return new Paginator($items, $total, $perPage, $page, $path);
                })
            ;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function cursorPaginate(int $perPage = 15, string $cursorColumn = 'id', ?string $path = null): PromiseInterface
    {
        $cursor = RequestHelper::getCursor();
        $path ??= RequestHelper::getCurrentPath();

        $query = CursorPaginationHelper::applyCursor($this, $cursor, $cursorColumn);

        return $query->limit($perPage + 1)->get()->then(function (array $results) use ($perPage, $cursorColumn, $path) {
            $hasMore = \count($results) > $perPage;
            if ($hasMore) {
                array_pop($results);
            }

            $nextCursor = CursorPaginationHelper::resolveNextCursor($results, $cursorColumn, $hasMore);

            return new CursorPaginator($results, $perPage, $nextCursor, $cursorColumn, $path);
        });
    }
}