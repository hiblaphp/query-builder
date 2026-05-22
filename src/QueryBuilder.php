<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
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

    /**
     * @param QueryInterface|array<string, mixed>|null $connection
     */
    public function __construct(
        QueryInterface|array|null $connection = null,
        ?string $driver = null,
        string $table = ''
    ) {
        if ($table !== '') {
            $this->table = $table;
        }

        if ($connection === null) {
            // Fallback to default DB Facade connection
            $dbConnection = DB::connection();
            $this->client = $dbConnection->getClient();
            $this->driver = $driver ?? $dbConnection->getDriverName();
        } elseif (\is_array($connection)) {
            // Ad-hoc configuration passed directly
            $this->driver = $driver ?? $connection['driver'] ?? 'mysql';
            $this->client = match ($this->driver) {
                'mysql', 'mysqli' => new \Hibla\Mysql\MysqlClient($connection),
                default => throw new \InvalidArgumentException("Driver '{$this->driver}' not supported yet."),
            };
        } else {
            // Injected directly (e.g., from DatabaseConnection or DatabaseTransaction wrappers)
            $this->client = $connection;
            $this->driver = $driver ?? 'mysql';
        }
    }

    /**
     * Override the primitive base class method to ensure subqueries
     * (like whereExists) inherit the exact same client and driver.
     */
    protected function newQuery(): static
    {
        return new static($this->client, $this->driver, '');
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
            ->then(fn (mixed $value) => (int) $value)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): PromiseInterface
    {
        return $this->count()->then(fn (int $count) => $count > 0);
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
        $path = $path ?? RequestHelper::getCurrentPath();

        // Promise Chaining: We first await the count(), then we fire the get() query
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
        $path = $path ?? RequestHelper::getCurrentPath();

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
