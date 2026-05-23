<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;

use function Hibla\async;
use function Hibla\await;

class SchemaBuilder
{
    private string $driver;

    private ?SQLiteSchemaBuilder $sqliteBuilder = null;

    private ?string $connection = null;

    public function __construct(?string $driver = null, ?string $connection = null)
    {
        $this->connection = $connection;
        $this->driver = $driver ?? $this->getConnection()->getDriverName();
    }

    /**
     * Get the database connection to use.
     */
    private function getConnection(): DatabaseConnectionInterface
    {
        return DB::connection($this->connection);
    }

    private function getSQLiteBuilder(): SQLiteSchemaBuilder
    {
        if ($this->sqliteBuilder === null) {
            $this->sqliteBuilder = new SQLiteSchemaBuilder($this->getCompiler());
        }

        return $this->sqliteBuilder;
    }

    private function getCompiler(): SchemaCompiler
    {
        return match ($this->driver) {
            'mysql' => new Compilers\MySQLSchemaCompiler(),
            'pgsql' => new Compilers\PostgreSQLSchemaCompiler(),
            'sqlite' => new Compilers\SQLiteSchemaCompiler(),
            default => new Compilers\MySQLSchemaCompiler(),
        };
    }

    /**
     * Create a new table on the schema.
     *
     * @return PromiseInterface<int|null>
     */
    public function create(string $table, callable $callback): PromiseInterface
    {
        return async(function () use ($table, $callback) {
            $blueprint = new Blueprint($table);
            $callback($blueprint);

            $this->processColumnIndexes($blueprint);

            $compiler = $this->getCompiler();
            $sql = $compiler->compileCreate($blueprint);

            if ($this->driver === 'sqlite') {
                return await($this->getSQLiteBuilder()->handleCreate($sql));
            }

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @return PromiseInterface<int>
     */
    public function dropIfExists(string $table): PromiseInterface
    {
        return async(function () use ($table) {
            $compiler = $this->getCompiler();
            $sql = $compiler->compileDropIfExists($table);

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Drop a table from the schema.
     *
     * @return PromiseInterface<int>
     */
    public function drop(string $table): PromiseInterface
    {
        return async(function () use ($table) {
            $compiler = $this->getCompiler();
            $sql = $compiler->compileDrop($table);

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Determine if the given table exists.
     *
     * @return PromiseInterface<mixed>
     */
    public function hasTable(string $table): PromiseInterface
    {
        return async(function () use ($table) {
            $compiler = $this->getCompiler();
            $sql = $compiler->compileTableExists($table);

            return await($this->getConnection()->rawValue($sql, []));
        });
    }

    /**
     * Modify a table on the schema.
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    public function table(string $table, callable $callback): PromiseInterface
    {
        return async(function () use ($table, $callback) {
            $blueprint = new Blueprint($table);
            $callback($blueprint);

            $this->processColumnIndexes($blueprint);

            $compiler = $this->getCompiler();

            if ($this->driver === 'sqlite') {
                return await($this->getSQLiteBuilder()->handleTable($table, $blueprint));
            }

            $sql = $compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                $results = [];
                foreach ($sql as $statement) {
                    $results[] = await($this->getConnection()->rawExecute($statement, []));
                }

                return \count($results) === 0 ? null : $results;
            }

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Rename a table on the schema.
     *
     * @return PromiseInterface<int>
     */
    public function rename(string $from, string $to): PromiseInterface
    {
        return async(function () use ($from, $to) {
            $compiler = $this->getCompiler();
            $sql = $compiler->compileRename($from, $to);

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Drop a column from a table.
     *
     * @param string|list<string> $columns
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    public function dropColumn(string $table, string|array $columns): PromiseInterface
    {
        return async(function () use ($table, $columns) {
            $blueprint = new Blueprint($table);
            $blueprint->dropColumn($columns);

            $compiler = $this->getCompiler();

            if ($this->driver === 'sqlite') {
                return await($this->getSQLiteBuilder()->handleDropColumn($table, $blueprint));
            }

            $sql = $compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                $results = [];
                foreach ($sql as $statement) {
                    $results[] = await($this->getConnection()->rawExecute($statement, []));
                }

                return \count($results) === 0 ? null : $results;
            }

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Rename a column on a table.
     *
     * @return PromiseInterface<int|list<int>>
     */
    public function renameColumn(string $table, string $from, string $to): PromiseInterface
    {
        return async(function () use ($table, $from, $to) {
            $blueprint = new Blueprint($table);
            $blueprint->renameColumn($from, $to);

            $compiler = $this->getCompiler();
            $sql = $compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                $results = [];
                foreach ($sql as $statement) {
                    $results[] = await($this->getConnection()->rawExecute($statement, []));
                }

                return $results;
            }

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Drop an index from a table.
     *
     * @param string|list<string> $index
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    public function dropIndex(string $table, string|array $index): PromiseInterface
    {
        return async(function () use ($table, $index) {
            $blueprint = new Blueprint($table);
            $blueprint->dropIndex($index);

            $compiler = $this->getCompiler();

            if ($this->driver === 'sqlite') {
                return await($this->getSQLiteBuilder()->handleDropIndex($table, $blueprint));
            }

            $sql = $compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                $results = [];
                foreach ($sql as $statement) {
                    $results[] = await($this->getConnection()->rawExecute($statement, []));
                }

                return \count($results) === 0 ? null : $results;
            }

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Drop a foreign key from a table.
     *
     * @param string|list<string> $foreignKey
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    public function dropForeign(string $table, string|array $foreignKey): PromiseInterface
    {
        return async(function () use ($table, $foreignKey) {
            $blueprint = new Blueprint($table);
            $blueprint->dropForeign($foreignKey);

            $compiler = $this->getCompiler();

            if ($this->driver === 'sqlite') {
                return await($this->getSQLiteBuilder()->handleDropForeign($table, $blueprint));
            }

            $sql = $compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                $results = [];
                foreach ($sql as $statement) {
                    $results[] = await($this->getConnection()->rawExecute($statement, []));
                }

                return \count($results) === 0 ? null : $results;
            }

            return await($this->getConnection()->rawExecute($sql, []));
        });
    }

    /**
     * Process column-level indexes and add them to the blueprint.
     */
    private function processColumnIndexes(Blueprint $blueprint): void
    {
        foreach ($blueprint->getColumns() as $column) {
            foreach ($column->getColumnIndexes() as $indexInfo) {
                $indexName = $indexInfo['name'] ?? $blueprint->getTable() . '_' . $column->getName() . '_' . strtolower($indexInfo['type']);

                $indexDef = new IndexDefinition($indexInfo['type'], [$column->getName()], $indexName);

                if (isset($indexInfo['algorithm'])) {
                    $indexDef->algorithm($indexInfo['algorithm']);
                }

                if (isset($indexInfo['operatorClass'])) {
                    $indexDef->operatorClass($indexInfo['operatorClass']);
                }

                $blueprint->addIndexDefinition($indexDef);
            }
        }
    }
}
