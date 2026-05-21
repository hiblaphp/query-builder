<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use function Hibla\async;
use function Hibla\await;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\ConnectionProxy;
use Hibla\QueryBuilder\DB;
use Rcalicdan\ConfigLoader\Config;

class SchemaBuilder
{
    private string $driver;
    private ?SQLiteSchemaBuilder $sqliteBuilder = null;
    private ?string $connection = null;

    public function __construct(?string $driver = null, ?string $connection = null)
    {
        $this->connection = $connection;
        $this->driver = $driver ?? $this->detectDriver();
    }

    private function detectDriver(): string
    {
        $dbConfig = Config::get('async-database');

        if (! is_array($dbConfig)) {
            return 'mysql';
        }

        $connectionName = $this->connection ?? ($dbConfig['default'] ?? 'mysql');
        if (! is_string($connectionName)) {
            return 'mysql';
        }

        $connections = $dbConfig['connections'] ?? [];
        if (! is_array($connections)) {
            return 'mysql';
        }

        $connectionConfig = $connections[$connectionName] ?? [];
        if (! is_array($connectionConfig)) {
            return 'mysql';
        }
        $driver = $connectionConfig['driver'] ?? 'mysql';

        return is_string($driver) ? strtolower($driver) : 'mysql';
    }

    private function getSQLiteBuilder(): SQLiteSchemaBuilder
    {
        if ($this->sqliteBuilder === null) {
            $this->sqliteBuilder = new SQLiteSchemaBuilder($this->getCompiler());
        }

        return $this->sqliteBuilder;
    }

    /**
     * Get the database connection to use.
     */
    private function getConnection(): ConnectionProxy
    {
        return DB::connection($this->connection);
    }

    /**
     * Create a new table on the schema.
     *
     * @return PromiseInterface<int|null>
     */
    public function create(string $table, callable $callback): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $this->processColumnIndexes($blueprint);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileCreate($blueprint);

        if ($this->driver === 'sqlite') {
            return $this->getSQLiteBuilder()->handleCreate($sql);
        }

        /** @phpstan-ignore-next-line */
        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @return PromiseInterface<int>
     */
    public function dropIfExists(string $table): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileDropIfExists($table);

        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Drop a table from the schema.
     *
     * @return PromiseInterface<int>
     */
    public function drop(string $table): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileDrop($table);

        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Determine if the given table exists.
     *
     * @return PromiseInterface<mixed>
     */
    public function hasTable(string $table): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileTableExists($table);

        return $this->getConnection()->rawValue($sql, []);
    }

    /**
     * Modify a table on the schema.
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    public function table(string $table, callable $callback): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $this->processColumnIndexes($blueprint);

        $compiler = $this->getCompiler();

        if ($this->driver === 'sqlite') {
            /** @phpstan-ignore-next-line */
            return $this->getSQLiteBuilder()->handleTable($table, $blueprint);
        }

        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            $statements = $this->toList($sql);

            return $this->executeMultipleOrNull($statements);
        }

        /** @phpstan-ignore-next-line */
        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Rename a table on the schema.
     *
     * @return PromiseInterface<int>
     */
    public function rename(string $from, string $to): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileRename($from, $to);

        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Drop a column from a table.
     *
     * @param string|list<string> $columns
     * @return PromiseInterface<int|list<int>|null>
     */
    public function dropColumn(string $table, string|array $columns): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropColumn($columns);

        $compiler = $this->getCompiler();

        if ($this->driver === 'sqlite') {
            /** @phpstan-ignore-next-line */
            return $this->getSQLiteBuilder()->handleDropColumn($table, $blueprint);
        }

        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            if (count($sql) === 0) {
                return $this->nullPromise();
            }
            $statements = $this->toList($sql);

            return $this->executeMultipleOrNull($statements);
        }

        /** @phpstan-ignore-next-line */
        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Rename a column on a table.
     *
     * @return PromiseInterface<int|list<int>>
     */
    public function renameColumn(string $table, string $from, string $to): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->renameColumn($from, $to);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            $statements = $this->toList($sql);

            return $this->executeMultipleNoNull($statements);
        }

        /** @phpstan-ignore-next-line */
        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Drop an index from a table.
     *
     * @param string|list<string> $index
     * @return PromiseInterface<int|list<int>|null>
     */
    public function dropIndex(string $table, string|array $index): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropIndex($index);

        $compiler = $this->getCompiler();

        if ($this->driver === 'sqlite') {
            /** @phpstan-ignore-next-line */
            return $this->getSQLiteBuilder()->handleDropIndex($table, $blueprint);
        }

        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            if (count($sql) === 0) {
                return $this->nullPromise();
            }
            $statements = $this->toList($sql);

            return $this->executeMultipleOrNull($statements);
        }

        /** @phpstan-ignore-next-line */
        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Drop a foreign key from a table.
     *
     * @param string|list<string> $foreignKey
     * @return PromiseInterface<int|list<int>|null>
     */
    public function dropForeign(string $table, string|array $foreignKey): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropForeign($foreignKey);

        $compiler = $this->getCompiler();

        if ($this->driver === 'sqlite') {
            /** @phpstan-ignore-next-line */
            return $this->getSQLiteBuilder()->handleDropForeign($table, $blueprint);
        }

        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            if (count($sql) === 0) {
                return $this->nullPromise();
            }
            $statements = $this->toList($sql);

            return $this->executeMultipleOrNull($statements);
        }

        /** @phpstan-ignore-next-line */
        return $this->getConnection()->rawExecute($sql, []);
    }

    /**
     * Convert array to a list type for PHPStan.
     *
     * @param array<mixed> $items
     * @return list<string>
     */
    private function toList(array $items): array
    {
        /** @var list<string> */
        return array_values($items);
    }

    /**
     * Create a null promise for empty operations.
     *
     * @return PromiseInterface<int|list<int>|null>
     */
    private function nullPromise(): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(static fn () => null);
    }

    /**
     * Execute multiple SQL statements, returning list of results or null if empty.
     *
     * @param list<string> $statements
     * @return PromiseInterface<int|list<int>|null>
     */
    private function executeMultipleOrNull(array $statements): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(function () use ($statements) {
            $results = [];
            foreach ($statements as $sql) {
                $result = await($this->getConnection()->rawExecute($sql, []));
                $results[] = $result;
            }

            return count($results) === 0 ? null : $results;
        });
    }

    /**
     * Execute multiple SQL statements, returning list of results.
     *
     * @param list<string> $statements
     * @return PromiseInterface<int|list<int>>
     */
    private function executeMultipleNoNull(array $statements): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(function () use ($statements) {
            $results = [];
            foreach ($statements as $sql) {
                $result = await($this->getConnection()->rawExecute($sql, []));
                $results[] = $result;
            }

            return $results;
        });
    }

    private function getCompiler(): SchemaCompiler
    {
        return match ($this->driver) {
            'mysql', 'mysqli' => new Compilers\MySQLSchemaCompiler(),
            'pgsql', 'pgsql_native' => new Compilers\PostgreSQLSchemaCompiler(),
            'sqlite' => new Compilers\SQLiteSchemaCompiler(),
            default => new Compilers\MySQLSchemaCompiler(),
        };
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
