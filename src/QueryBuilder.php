<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\Interfaces\ConnectionResolverInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
use Hibla\QueryBuilder\Utilities\CursorPaginationHelper;
use Hibla\QueryBuilder\Utilities\RequestHelper;
use Hibla\Sql\QueryInterface;
use Hibla\Sql\Result;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

class QueryBuilder extends QueryBuilderBase implements QueryBuilderInterface
{
    private readonly QueryInterface $client;

    private bool $returnAsObject = false;

    private static ?ConnectionResolverInterface $resolver = null;

    /**
     * Inject the connection resolver bridge.
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
            $this->client = self::$resolver->resolveClientFromConfig($connection);
            $this->driver = $driver ?? $connection['driver'] ?? 'mysql';
        } else {
            $this->ensureResolverIsSet();
            $conn = self::$resolver->connection($connection);
            $this->client = $conn->getClient();
            $this->driver = $driver ?? $conn->getDriverName();
        }
    }

    private function ensureResolverIsSet(): void
    {
        if (self::$resolver === null) {
            throw new \RuntimeException(
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
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->client->query($sql, $bindings)
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
        return $this->client->fetchOne($sql, $bindings)
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
        return array_map(static fn(array $row): object => (object) $row, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function get(): PromiseInterface
    {
        $sql = $this->buildSelectQuery();

        return $this->client->query($sql, $this->getCompiledBindings())
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

        return $instanceWithLimit->client->fetchOne($sql, $instanceWithLimit->getCompiledBindings())
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

                throw new \RuntimeException("Record not found with {$column} = {$idString}");
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

            return \is_object($result) ? ($result->$column ?? null) : ($result[$column] ?? null);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $column = '*'): PromiseInterface
    {
        $sql = $this->buildCountQuery($column);

        return $this->client->fetchValue($sql, null, $this->getCompiledBindings())
            ->then(fn(mixed $value) => (int) $value)
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
            $bindings = array_merge($bindings, array_values($row));
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
        $bindings = array_merge(array_values($data), $this->getCompiledBindings());

        return $this->client->execute($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(): PromiseInterface
    {
        $sql = $this->buildDeleteQuery();

        return $this->client->execute($sql, $this->getCompiledBindings());
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
