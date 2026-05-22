<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

interface SchemaCompiler
{
    /**
     * Compile a CREATE TABLE statement.
     *
     * @param Blueprint $blueprint
     *
     * @return string
     */
    public function compileCreate(Blueprint $blueprint): string;

    /**
     * Compile an ALTER TABLE statement.
     *
     * @param Blueprint $blueprint
     *
     * @return string|list<string>
     */
    public function compileAlter(Blueprint $blueprint): string|array;

    /**
     * Compile a DROP TABLE statement.
     *
     * @param string $table
     *
     * @return string
     */
    public function compileDrop(string $table): string;

    /**
     * Compile a DROP TABLE IF EXISTS statement.
     *
     * @param string $table
     *
     * @return string
     */
    public function compileDropIfExists(string $table): string;

    /**
     * Compile a table existence check query.
     *
     * @param string $table
     *
     * @return string
     */
    public function compileTableExists(string $table): string;

    /**
     * Compile a RENAME TABLE statement.
     *
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    public function compileRename(string $from, string $to): string;

    /**
     * Compile a DROP COLUMN statement.
     *
     * @param Blueprint $blueprint
     * @param list<string> $columns
     *
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, array $columns): string;

    /**
     * Compile a RENAME COLUMN statement.
     *
     * @param Blueprint $blueprint
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string;
}
