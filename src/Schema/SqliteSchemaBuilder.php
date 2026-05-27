<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\DB;

use function Hibla\async;
use function Hibla\await;

class SQLiteSchemaBuilder
{
    private SchemaCompiler $compiler;

    public function __construct(SchemaCompiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Handle CREATE TABLE for SQLite.
     *
     * @param string $sql
     *
     * @return PromiseInterface<int|null>
     */
    public function handleCreate(string $sql): PromiseInterface
    {
        return async(function () use ($sql) {
            await(DB::rawExecute('PRAGMA foreign_keys = ON', []));

            return await(DB::rawExecute($sql, []));
        });
    }

    /**
     * Handle table modification for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     *
     * @return PromiseInterface<int|list<int>|null|bool>
     */
    public function handleTable(string $table, Blueprint $blueprint): PromiseInterface
    {
        $needsRecreation = \count($blueprint->getDropColumns()) > 0 ||
            \count($blueprint->getModifyColumns()) > 0 ||
            \count($blueprint->getDropForeignKeys()) > 0 ||
            \count($blueprint->getDropIndexes()) > 0;

        if (! $needsRecreation) {
            return $this->executeAlter($blueprint);
        }

        return $this->handleTableRecreation($table, $blueprint);
    }

    /**
     * Handle DROP COLUMN for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropColumn(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    /**
     * Handle DROP INDEX for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropIndex(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    /**
     * Handle DROP FOREIGN KEY for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropForeign(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    /**
     * Execute table recreation for schema modifications.
     *
     * @param string $table
     * @param Blueprint $blueprint
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function handleTableRecreation(string $table, Blueprint $blueprint): PromiseInterface
    {
        return async(function () use ($table, $blueprint) {
            $existingColumns = await(DB::raw("PRAGMA table_info(`{$table}`)", []));

            if (method_exists($this->compiler, 'setExistingTableColumns')) {
                $this->compiler->setExistingTableColumns($existingColumns);
            }

            await(DB::rawExecute('PRAGMA foreign_keys = ON', []));

            $sql = $this->compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                return await($this->executeStatements($sql));
            }

            return await(DB::rawExecute($sql, []));
        });
    }

    /**
     * Execute table recreation.
     *
     * @param string $table
     * @param Blueprint $blueprint
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function executeTableRecreation(string $table, Blueprint $blueprint): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(function () use ($table, $blueprint) {
            $existingColumns = await(DB::raw("PRAGMA table_info(`{$table}`)", []));

            if (method_exists($this->compiler, 'setExistingTableColumns')) {
                $this->compiler->setExistingTableColumns($existingColumns);
            }

            await(DB::rawExecute('PRAGMA foreign_keys = ON', []));

            $sql = $this->compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                return \count($sql) === 0 ? true : $this->executeStatements($sql);
            }

            return await(DB::rawExecute($sql, []));
        });
    }

    /**
     * Execute ALTER TABLE statements.
     *
     * @param Blueprint $blueprint
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function executeAlter(Blueprint $blueprint): PromiseInterface
    {
        return async(function () use ($blueprint) {
            $sql = $this->compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                return \count($sql) === 0 ? true : await($this->executeMultiple($sql));
            }

            return await(DB::rawExecute($sql, []));
        });
    }

    /**
     * Execute a list of SQL statements.
     *
     * @param list<string> $statements
     *
     * @return PromiseInterface<bool>
     */
    private function executeStatements(array $statements): PromiseInterface
    {
        return async(function () use ($statements) {
            try {
                foreach ($statements as $statement) {
                    await(DB::rawExecute($statement, []));
                }

                return true;
            } catch (\Throwable $e) {
                try {
                    await(DB::rawExecute('ROLLBACK', []));
                } catch (\Throwable $rollbackError) {
                }

                throw $e;
            }
        });
    }

    /**
     * Execute multiple SQL statements and return results.
     *
     * @param list<string> $statements
     *
     * @return PromiseInterface<list<int>>
     */
    private function executeMultiple(array $statements): PromiseInterface
    {
        return async(function () use ($statements) {
            $results = [];
            foreach ($statements as $sql) {
                $results[] = await(DB::rawExecute($sql, []));
            }

            /** @var list<int> */
            return $results;
        });
    }
}
