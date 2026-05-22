<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

use Hibla\AsyncPDO\AsyncPDOConnection;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\Adapters\PdoAdapter;
use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Interfaces\ConnectionInterface;
use Hibla\QueryBuilder\Pagination\CursorPaginator;
use Hibla\QueryBuilder\Pagination\Paginator;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

/**
 * Async Query Builder for an easy way to write asynchronous SQL queries.
 *
 * This query builder is fully immutable. Each method that modifies the query
 * returns a new instance instead of modifying the current one, ensuring a
 * predictable and safe state management.
 */
class Builder extends QueryBuilderBase
{
    private bool $returnAsObject = false;

    private ?AsyncPDOConnection $connection = null;

    private ?ConnectionInterface $connectionAdapter = null;

    /**
     * Create a new AsyncQueryBuilder instance.
     *
     * @param string $table The table name to query.
     * @param AsyncPDOConnection|null $connection Optional connection instance
     */
    final public function __construct(string $table = '', ?AsyncPDOConnection $connection = null)
    {
        if ($table !== '') {
            $this->table = $table;
        }

        $this->connection = $connection;
        $this->driver = BuilderConfiguration::detectDriver();
        BuilderConfiguration::configureTemplates();
    }

    /**
     * Set the connection for this builder instance.
     */
    public function setConnection(AsyncPDOConnection $connection): static
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }

    /**
     * Set the connection adapter for this builder instance.
     */
    public function setConnectionAdapter(ConnectionInterface $adapter): static
    {
        $clone = clone $this;
        $clone->connectionAdapter = $adapter;

        if ($adapter->getDriver() !== '') {
            $clone->driver = $adapter->getDriver();
        }

        return $clone;
    }

    /**
     * Get the connection adapter instance.
     */
    private function getConnectionAdapter(): ConnectionInterface
    {
        if ($this->connectionAdapter !== null) {
            return $this->connectionAdapter;
        }

        if ($this->connection !== null) {
            $this->connectionAdapter = new PdoAdapter(
                [],
                10
            );

            return $this->connectionAdapter;
        }

        try {
            $proxy = DB::connection();
            $connection = $proxy->getConnection();

            $this->connectionAdapter = $connection;

            return $connection;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'No connection available. Ensure database configuration is loaded properly. Error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Reset the driver cache. Useful for testing or when switching connections.
     */
    public static function resetDriverCache(): void
    {
        BuilderConfiguration::reset();
    }

    /**
     * Set the query to return results as objects instead of arrays.
     */
    public function toObject(): static
    {
        $clone = clone $this;
        $clone->returnAsObject = true;

        return $clone;
    }

    /**
     * Set the query to return results as arrays instead of objects.
     */
    public function toArray(): static
    {
        $clone = clone $this;
        $clone->returnAsObject = false;

        return $clone;
    }

    /**
     * Convert array results to objects.
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
     * Execute the query and return all results.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>|array<int, object>>
     */
    public function get(): PromiseInterface
    {
        $sql = $this->buildSelectQuery();

        return async(function () use ($sql): array {
            $results = await($this->getConnectionAdapter()->query($sql, $this->getCompiledBindings()));

            return $this->returnAsObject ? $this->convertToObjects($results) : $results;
        });
    }

    /**
     * Get the first result from the query.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass|false>
     */
    public function first(): PromiseInterface
    {
        $instanceWithLimit = $this->limit(1);
        $sql = $instanceWithLimit->buildSelectQuery();

        return async(function () use ($sql, $instanceWithLimit): array|\stdClass|false {
            $result = await($instanceWithLimit->getConnectionAdapter()->fetchOne($sql, $instanceWithLimit->getCompiledBindings()));

            if ($result === false) {
                return false;
            }

            return $this->returnAsObject ? (object) $result : $result;
        });
    }

    /**
     * Find a record by ID.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass|false>
     */
    public function find(mixed $id, string $column = 'id'): PromiseInterface
    {
        return $this->where($column, $id)->first();
    }

    /**
     * Find a record by ID or throw an exception if not found.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass>
     *
     * @throws \RuntimeException When no record is found.
     */
    public function findOrFail(mixed $id, string $column = 'id'): PromiseInterface
    {
        return async(function () use ($id, $column): array|\stdClass {
            $result = await($this->find($id, $column));
            if ($result === null || $result === false) {
                $idString = \is_scalar($id) ? (string) $id : 'complex_type';

                throw new \RuntimeException("Record not found with {$column} = {$idString}");
            }

            return $result;
        });
    }

    /**
     * Get a single value from the first result.
     *
     * @return PromiseInterface<mixed>
     */
    public function value(string $column): PromiseInterface
    {
        return async(function () use ($column): mixed {
            $result = await($this->select($column)->first());

            if ($result === false) {
                return null;
            }

            if (\is_array($result)) {
                return $result[$column] ?? null;
            }

            return $result->$column ?? null;
        });
    }

    /**
     * Map the query results using a callback function.
     *
     * @param callable(array<string, mixed>|object): mixed $callback
     *
     * @return PromiseInterface<array<int, mixed>>
     */
    public function map(callable $callback): PromiseInterface
    {
        return async(function () use ($callback): array {
            $results = await($this->get());

            return array_map($callback, $results);
        });
    }

    /**
     * Get the first result and map it using a callback function.
     *
     * @param callable(array<string, mixed>|object): mixed $callback
     *
     * @return PromiseInterface<mixed|false>
     */
    public function firstMap(callable $callback): PromiseInterface
    {
        return async(function () use ($callback): mixed {
            $result = await($this->first());

            return $result !== false ? $callback($result) : false;
        });
    }

    /**
     * Count the number of records.
     *
     * @return PromiseInterface<int>
     */
    public function count(string $column = '*'): PromiseInterface
    {
        $sql = $this->buildCountQuery($column);

        /** @var PromiseInterface<int> */
        return $this->getConnectionAdapter()->fetchValue($sql, $this->getCompiledBindings());
    }

    /**
     * Get the maximum value of a column.
     *
     * @param string $column The column to get max value from
     *
     * @return PromiseInterface<mixed> A promise that resolves to the maximum value
     */
    public function max(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('MAX', $column);

        return $this->getConnectionAdapter()->fetchValue($sql, $this->getCompiledBindings());
    }

    /**
     * Get the minimum value of a column.
     *
     * @param string $column The column to get min value from
     *
     * @return PromiseInterface<mixed> A promise that resolves to the minimum value
     */
    public function min(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('MIN', $column);

        return $this->getConnectionAdapter()->fetchValue($sql, $this->getCompiledBindings());
    }

    /**
     * Get the average value of a column.
     *
     * @param string $column The column to get average from
     *
     * @return PromiseInterface<mixed> A promise that resolves to the average value
     */
    public function avg(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('AVG', $column);

        return $this->getConnectionAdapter()->fetchValue($sql, $this->getCompiledBindings());
    }

    /**
     * Get the sum of a column.
     *
     * @param string $column The column to sum
     *
     * @return PromiseInterface<mixed> A promise that resolves to the sum value
     */
    public function sum(string $column): PromiseInterface
    {
        $sql = $this->buildAggregateQuery('SUM', $column);

        return $this->getConnectionAdapter()->fetchValue($sql, $this->getCompiledBindings());
    }

    /**
     * Check if any records exist.
     *
     * @return PromiseInterface<bool>
     */
    public function exists(): PromiseInterface
    {
        return async(function (): bool {
            $count = await($this->count());

            return $count > 0;
        });
    }

    /**
     * Insert a single record.
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int>
     */
    public function insert(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildInsertQuery($data);

        return $this->getConnectionAdapter()->execute($sql, array_values($data));
    }

    /**
     * Insert or update a record based on unique columns.
     *
     * @param array<string, mixed>|array<array<string, mixed>> $data
     * @param array<string> $uniqueColumns
     * @param array<string> $updateColumns
     *
     * @return PromiseInterface<int>
     */
    public function upsert(array $data, array $uniqueColumns, array $updateColumns): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }

        $sql = $this->buildUpsertQuery($data, $uniqueColumns, $updateColumns);
        $params = $this->flattenBatchParameters($data);

        return $this->getConnectionAdapter()->execute($sql, $params);
    }

    /**
     * Flatten batch parameters from nested arrays to a single flat array.
     *
     * @param array<string, mixed>|array<int, array<string, mixed>> $data
     *
     * @return array<int, mixed>
     */
    protected function flattenBatchParameters(array $data): array
    {
        $firstItem = reset($data);
        $isBatch = \is_array($firstItem) && ! isset($firstItem[0]);

        if (! $isBatch) {
            return array_values($data);
        }

        $flattened = [];
        /** @var array<string, mixed> $row */
        foreach ($data as $row) {
            $flattened = array_merge($flattened, array_values($row));
        }

        return $flattened;
    }

    /**
     * Insert multiple records in a batch operation.
     *
     * @param array<array<string, mixed>> $data
     *
     * @return PromiseInterface<int>
     */
    public function insertBatch(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }

        $sql = $this->buildInsertBatchQuery($data);
        $bindings = [];
        foreach ($data as $row) {
            if (\is_array($row)) {
                $bindings = array_merge($bindings, array_values($row));
            }
        }

        return $this->getConnectionAdapter()->execute($sql, $bindings);
    }

    /**
     * Create a new record (alias for insert).
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int>
     */
    public function create(array $data): PromiseInterface
    {
        return $this->insert($data);
    }

    /**
     * Update records matching the query conditions.
     *
     * @param array<string, mixed> $data
     *
     * @return PromiseInterface<int>
     */
    public function update(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildUpdateQuery($data);
        $whereBindings = $this->getCompiledBindings();
        $bindings = array_merge(array_values($data), $whereBindings);

        return $this->getConnectionAdapter()->execute($sql, $bindings);
    }

    /**
     * Delete records matching the query conditions.
     *
     * @return PromiseInterface<int>
     */
    public function delete(): PromiseInterface
    {
        $sql = $this->buildDeleteQuery();

        return $this->getConnectionAdapter()->execute($sql, $this->getCompiledBindings());
    }

    /**
     * Paginate the results with automatic request handling.
     *
     * @return PromiseInterface<Paginator>
     */
    public function paginate(int $perPage = 15, ?string $path = null): PromiseInterface
    {
        $page = RequestHelper::getCurrentPage();

        if ($path === null) {
            $path = RequestHelper::getCurrentPath();
        }

        return async(function () use ($perPage, $page, $path): Paginator {
            $total = await($this->count());
            $results = await($this->forPage($page, $perPage)->get());

            return new Paginator(
                items: $results,
                total: $total,
                perPage: $perPage,
                currentPage: $page,
                path: $path,
            );
        });
    }

    /**
     * Paginate with cursor-based pagination (automatic request handling).
     *
     * @return PromiseInterface<CursorPaginator>
     */
    public function cursorPaginate(
        int $perPage = 15,
        string $cursorColumn = 'id',
        ?string $path = null,
    ): PromiseInterface {
        $cursor = RequestHelper::getCursor();

        if ($path === null) {
            $path = RequestHelper::getCurrentPath();
        }

        return async(function () use ($perPage, $cursor, $cursorColumn, $path): CursorPaginator {
            $query = CursorPaginationHelper::applyCursor($this, $cursor, $cursorColumn);
            $results = await($query->limit($perPage + 1)->get());

            $hasMore = \count($results) > $perPage;
            if ($hasMore) {
                array_pop($results);
            }

            $nextCursor = CursorPaginationHelper::resolveNextCursor($results, $cursorColumn, $hasMore);

            return new CursorPaginator(
                items: $results,
                perPage: $perPage,
                nextCursor: $nextCursor,
                cursorColumn: $cursorColumn,
                path: $path,
            );
        });
    }
}
