<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use Rcalicdan\ConfigLoader\Config;

class Blueprint
{
    private string $table;

    /**
     * @var array<int, Column>
     */
    private array $columns = [];

    /**
     * @var list<array{type: string, name: string, columns: list<string>}>
     */
    private array $indexes = [];

    /**
     * @var array<int, IndexDefinition>
     */
    private array $indexDefinitions = [];

    /**
     * @var array<int, ForeignKey>
     */
    private array $foreignKeys = [];

    private string $engine = 'InnoDB';

    private string $charset = 'utf8mb4';

    private string $collation = 'utf8mb4_unicode_ci';

    /**
     * @var list<array{type: 'rename', to: string}>
     */
    private array $commands = [];

    /**
     * @var list<string>
     */
    private array $dropColumns = [];

    /**
     * @var list<array{from: string, to: string}>
     */
    private array $renameColumns = [];

    /**
     * @var array<int, Column>
     */
    private array $modifyColumns = [];

    /**
     * @var list<list<string>>
     */
    private array $dropIndexes = [];

    /**
     * @var list<string>
     */
    private array $dropForeignKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Add an index definition to the blueprint.
     */
    public function addIndexDefinition(IndexDefinition $indexDefinition): void
    {
        $this->indexDefinitions[] = $indexDefinition;
    }

    /**
     * Add a column to the blueprint.
     */
    public function addColumn(Column $column): void
    {
        $this->columns[] = $column;
    }

    /**
     * Add a foreign key to the blueprint.
     */
    public function addForeignKey(ForeignKey $foreignKey): void
    {
        $this->foreignKeys[] = $foreignKey;
    }

    /**
     * Get the table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the columns for the blueprint.
     *
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the indexes for the blueprint.
     *
     * @return list<array{type: string, name: string, columns: list<string>}>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Get the index definitions for the blueprint.
     *
     * @return array<int, IndexDefinition>
     */
    public function getIndexDefinitions(): array
    {
        return $this->indexDefinitions;
    }

    /**
     * Get the foreign keys for the blueprint.
     *
     * @return array<int, ForeignKey>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Get the storage engine for the table.
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * Get the character set for the table.
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Get the collation for the table.
     */
    public function getCollation(): string
    {
        return $this->collation;
    }

    /**
     * Get the commands for the blueprint.
     *
     * @return list<array{type: 'rename', to: string}>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get the columns to be dropped.
     *
     * @return list<string>
     */
    public function getDropColumns(): array
    {
        return $this->dropColumns;
    }

    /**
     * Get the columns to be renamed.
     *
     * @return list<array{from: string, to: string}>
     */
    public function getRenameColumns(): array
    {
        return $this->renameColumns;
    }

    /**
     * Get the columns to be modified.
     *
     * @return array<int, Column>
     */
    public function getModifyColumns(): array
    {
        return $this->modifyColumns;
    }

    /**
     * Get the indexes to be dropped.
     *
     * @return list<list<string>>
     */
    public function getDropIndexes(): array
    {
        return $this->dropIndexes;
    }

    /**
     * Get the foreign keys to be dropped.
     *
     * @return list<string>
     */
    public function getDropForeignKeys(): array
    {
        return $this->dropForeignKeys;
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) primary key column.
     */
    public function id(string $name = 'id'): Column
    {
        $column = $this->bigIncrements($name);
        $column->primary();

        return $column;
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column.
     */
    public function bigIncrements(string $name): Column
    {
        $column = new Column($name, 'BIGINT');
        $column->unsigned()->autoIncrement();
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column.
     */
    public function increments(string $name): Column
    {
        $column = new Column($name, 'INT');
        $column->unsigned()->autoIncrement();
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new auto-incrementing medium integer (3-byte) column.
     */
    public function mediumIncrements(string $name): Column
    {
        $column = new Column($name, 'MEDIUMINT');
        $column->unsigned()->autoIncrement();
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new auto-incrementing small integer (2-byte) column.
     */
    public function smallIncrements(string $name): Column
    {
        $column = new Column($name, 'SMALLINT');
        $column->unsigned()->autoIncrement();
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new auto-incrementing tiny integer (1-byte) column.
     */
    public function tinyIncrements(string $name): Column
    {
        $column = new Column($name, 'TINYINT');
        $column->unsigned()->autoIncrement();
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new big integer (8-byte) column.
     */
    public function bigInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'BIGINT');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new integer (4-byte) column.
     */
    public function integer(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'INT');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new medium integer (3-byte) column.
     */
    public function mediumInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'MEDIUMINT');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new small integer (2-byte) column.
     */
    public function smallInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'SMALLINT');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new tiny integer (1-byte) column.
     */
    public function tinyInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'TINYINT');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new unsigned big integer (8-byte) column.
     */
    public function unsignedBigInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->bigInteger($name, $autoIncrement, true);
    }

    /**
     * Create a new unsigned integer (4-byte) column.
     */
    public function unsignedInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->integer($name, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer (3-byte) column.
     */
    public function unsignedMediumInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->mediumInteger($name, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer (2-byte) column.
     */
    public function unsignedSmallInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->smallInteger($name, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column.
     */
    public function unsignedTinyInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->tinyInteger($name, $autoIncrement, true);
    }

    /**
     * Create a new string column.
     */
    public function string(string $name, int $length = 255): Column
    {
        $column = new Column($name, 'VARCHAR', $length);
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new text column.
     */
    public function text(string $name): Column
    {
        $column = new Column($name, 'TEXT');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new medium text column.
     */
    public function mediumText(string $name): Column
    {
        $column = new Column($name, 'MEDIUMTEXT');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new long text column.
     */
    public function longText(string $name): Column
    {
        $column = new Column($name, 'LONGTEXT');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new decimal column.
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2, bool $unsigned = false): Column
    {
        $column = new Column($name, 'DECIMAL', null, $precision, $scale);
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new float column.
     */
    public function float(string $name, int $precision = 8, int $scale = 2, bool $unsigned = false): Column
    {
        $column = new Column($name, 'FLOAT', null, $precision, $scale);
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new double column.
     */
    public function double(string $name, int $precision = 8, int $scale = 2, bool $unsigned = false): Column
    {
        $column = new Column($name, 'DOUBLE', null, $precision, $scale);
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new unsigned decimal column.
     */
    public function unsignedDecimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->decimal($name, $precision, $scale, true);
    }

    /**
     * Create a new boolean column.
     */
    public function boolean(string $name): Column
    {
        $column = new Column($name, 'TINYINT', 1);
        $column->default(0);
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new date column.
     */
    public function date(string $name): Column
    {
        $column = new Column($name, 'DATE');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new date-time column.
     */
    public function dateTime(string $name): Column
    {
        $column = new Column($name, 'DATETIME');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new timestamp column.
     */
    public function timestamp(string $name): Column
    {
        $column = new Column($name, 'TIMESTAMP');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Add nullable created_at and updated_at timestamp columns with timezone support.
     */
    public function timestamps(?string $timezone = null): void
    {
        if ($timezone === null) {
            $config = Config::loadFromRoot('hibla-migrations');
            $timezone = (\is_array($config) && isset($config['timezone']) && \is_string($config['timezone']))
                ? $config['timezone']
                : 'UTC';
        }

        $this->timestamp('created_at')->nullable()->useCurrent()->timezone($timezone);
        $this->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate()->timezone($timezone);
    }

    /**
     * Add a nullable deleted_at timestamp column for soft deletes.
     */
    public function softDeletes(string $column = 'deleted_at'): Column
    {
        return $this->timestamp($column)->nullable();
    }

    /**
     * Create a new json column.
     */
    public function json(string $name): Column
    {
        $column = new Column($name, 'JSON');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new enum column.
     *
     * @param list<string> $values
     */
    public function enum(string $name, array $values): Column
    {
        $column = new Column($name, 'ENUM');
        $column->setEnumValues($values);
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new unsigned big integer column for a foreign ID.
     */
    public function foreignId(string $name): Column
    {
        return $this->unsignedBigInteger($name);
    }

    /**
     * Specify a primary key for the table.
     *
     * @param string|list<string> $columns
     */
    public function primary(string|array $columns, ?string $name = null, ?string $algorithm = null): IndexDefinition
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_primary';

        $indexDef = new IndexDefinition('PRIMARY', $columns, $name);
        if ($algorithm !== null) {
            $indexDef->algorithm($algorithm);
        }

        $this->indexes[] = ['type' => 'PRIMARY', 'name' => $name, 'columns' => $columns];
        $this->indexDefinitions[] = $indexDef;

        return $indexDef;
    }

    /**
     * Specify a unique index for the table.
     *
     * @param string|list<string> $columns
     */
    public function unique(string|array $columns, ?string $name = null, ?string $algorithm = null): IndexDefinition
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_unique';

        $indexDef = new IndexDefinition('UNIQUE', $columns, $name);
        if ($algorithm !== null) {
            $indexDef->algorithm($algorithm);
        }

        $this->indexes[] = ['type' => 'UNIQUE', 'name' => $name, 'columns' => $columns];
        $this->indexDefinitions[] = $indexDef;

        return $indexDef;
    }

    /**
     * Specify an index for the table.
     *
     * @param string|list<string> $columns
     */
    public function index(string|array $columns, ?string $name = null, ?string $algorithm = null): IndexDefinition
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_index';

        $indexDef = new IndexDefinition('INDEX', $columns, $name);
        if ($algorithm !== null) {
            $indexDef->algorithm($algorithm);
        }

        $this->indexes[] = ['type' => 'INDEX', 'name' => $name, 'columns' => $columns];
        $this->indexDefinitions[] = $indexDef;

        return $indexDef;
    }

    /**
     * Specify a fulltext index for the table.
     *
     * @param string|list<string> $columns
     */
    public function fullText(string|array $columns, ?string $name = null, ?string $algorithm = null): IndexDefinition
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_fulltext';

        $indexDef = new IndexDefinition('FULLTEXT', $columns, $name);
        if ($algorithm !== null) {
            $indexDef->algorithm($algorithm);
        }

        $this->indexes[] = ['type' => 'FULLTEXT', 'name' => $name, 'columns' => $columns];
        $this->indexDefinitions[] = $indexDef;

        return $indexDef;
    }

    /**
     * Specify a spatial index for the table.
     *
     * @param string|list<string> $columns
     */
    public function spatialIndex(string|array $columns, ?string $name = null, ?string $operatorClass = null): IndexDefinition
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_spatial';

        $indexDef = new IndexDefinition('SPATIAL', $columns, $name);
        if ($operatorClass !== null) {
            $indexDef->operatorClass($operatorClass);
        }

        $this->indexes[] = ['type' => 'SPATIAL', 'name' => $name, 'columns' => $columns];
        $this->indexDefinitions[] = $indexDef;

        return $indexDef;
    }

    /**
     * Specify a raw index for the table.
     */
    public function rawIndex(string $expression, string $name): IndexDefinition
    {
        $indexDef = new IndexDefinition('RAW', [$expression], $name);
        $this->indexDefinitions[] = $indexDef;

        return $indexDef;
    }

    /**
     * Define a foreign key constraint.
     *
     * @param string|list<string> $columns
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKey
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_foreign';
        $foreignKey = new ForeignKey($name, $columns, $this->table);
        $this->foreignKeys[] = $foreignKey;

        return $foreignKey;
    }

    /**
     * Specify a column to be dropped.
     *
     * @param string|list<string> $columns
     */
    public function dropColumn(string|array $columns): self
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $this->dropColumns = array_merge($this->dropColumns, $columns);

        return $this;
    }

    /**
     * Specify a column to be renamed.
     */
    public function renameColumn(string $from, string $to): self
    {
        $this->renameColumns[] = ['from' => $from, 'to' => $to];

        return $this;
    }

    /**
     * Specify a string column to be modified.
     */
    public function modifyString(string $name, int $length = 255): Column
    {
        $column = new Column($name, 'VARCHAR', $length);
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify an integer column to be modified.
     */
    public function modifyInteger(string $name, bool $unsigned = false): Column
    {
        $column = new Column($name, 'INT');
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify a big integer column to be modified.
     */
    public function modifyBigInteger(string $name, bool $unsigned = false): Column
    {
        $column = new Column($name, 'BIGINT');
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify a small integer column to be modified.
     */
    public function modifySmallInteger(string $name, bool $unsigned = false): Column
    {
        $column = new Column($name, 'SMALLINT');
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify a tiny integer column to be modified.
     */
    public function modifyTinyInteger(string $name, bool $unsigned = false): Column
    {
        $column = new Column($name, 'TINYINT');
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify a text column to be modified.
     */
    public function modifyText(string $name): Column
    {
        $column = new Column($name, 'TEXT');
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify a decimal column to be modified.
     */
    public function modifyDecimal(string $name, int $precision = 8, int $scale = 2, bool $unsigned = false): Column
    {
        $column = new Column($name, 'DECIMAL', null, $precision, $scale);
        if ($unsigned) {
            $column->unsigned();
        }
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify a boolean column to be modified.
     */
    public function modifyBoolean(string $name): Column
    {
        $column = new Column($name, 'TINYINT', 1);
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;

        return $column;
    }

    /**
     * Specify columns to be dropped if they exist.
     *
     * @param string|list<string> $columns
     */
    public function dropIfExists(string|array $columns): self
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $this->dropColumns = array_merge($this->dropColumns, $columns);

        return $this;
    }

    /**
     * Specify an index to be dropped.
     *
     * @param string|list<string> $index
     */
    public function dropIndex(string|array $index): self
    {
        $this->dropIndexes[] = \is_array($index) ? $index : [$index];

        return $this;
    }

    /**
     * Specify a unique index to be dropped.
     *
     * @param string|list<string> $index
     */
    public function dropUnique(string|array $index): self
    {
        return $this->dropIndex($index);
    }

    /**
     * Specify the primary key to be dropped.
     */
    public function dropPrimary(?string $index = null): self
    {
        $this->dropIndexes[] = $index !== null ? [$index] : ['PRIMARY'];

        return $this;
    }

    /**
     * Specify a foreign key to be dropped.
     *
     * @param string|list<string> $index
     */
    public function dropForeign(string|array $index): self
    {
        $keys = \is_array($index) ? $index : [$index];
        $this->dropForeignKeys = array_merge($this->dropForeignKeys, $keys);

        return $this;
    }

    /**
     * Specify the new name for the table.
     */
    public function rename(string $to): self
    {
        $this->commands[] = ['type' => 'rename', 'to' => $to];

        return $this;
    }

    /**
     * Specify the storage engine for the table.
     */
    public function engine(string $engine): self
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * Specify the character set for the table.
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Specify the collation for the table.
     */
    public function collation(string $collation): self
    {
        $this->collation = $collation;

        return $this;
    }

    /**
     * Create a new point column.
     */
    public function point(string $name): Column
    {
        $column = new Column($name, 'POINT');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new linestring column.
     */
    public function lineString(string $name): Column
    {
        $column = new Column($name, 'LINESTRING');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new polygon column.
     */
    public function polygon(string $name): Column
    {
        $column = new Column($name, 'POLYGON');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new geometry column.
     */
    public function geometry(string $name): Column
    {
        $column = new Column($name, 'GEOMETRY');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new multipoint column.
     */
    public function multiPoint(string $name): Column
    {
        $column = new Column($name, 'MULTIPOINT');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new multilinestring column.
     */
    public function multiLineString(string $name): Column
    {
        $column = new Column($name, 'MULTILINESTRING');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new multipolygon column.
     */
    public function multiPolygon(string $name): Column
    {
        $column = new Column($name, 'MULTIPOLYGON');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new geometrycollection column.
     */
    public function geometryCollection(string $name): Column
    {
        $column = new Column($name, 'GEOMETRYCOLLECTION');
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create a new vector column (PostgreSQL only).
     */
    public function vector(string $name, int $dimensions = 1536): Column
    {
        $column = new Column($name, 'VECTOR', $dimensions);
        $column->setBlueprint($this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * Specify a vector index for the table (PostgreSQL only).
     *
     * @param string|list<string> $columns
     */
    public function vectorIndex(string|array $columns, ?string $name = null, ?string $distanceMetric = 'COSINE'): IndexDefinition
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_vector';

        $indexDef = new IndexDefinition('VECTOR', $columns, $name);
        if ($distanceMetric !== null) {
            $indexDef->algorithm($distanceMetric);
        }

        $this->indexes[] = ['type' => 'VECTOR', 'name' => $name, 'columns' => $columns];
        $this->indexDefinitions[] = $indexDef;

        return $indexDef;
    }
}
