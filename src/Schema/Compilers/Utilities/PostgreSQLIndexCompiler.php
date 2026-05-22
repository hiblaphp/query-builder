<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\Column;
use Hibla\QueryBuilder\Schema\IndexDefinition;

class PostgreSQLIndexCompiler extends IndexCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '", "';
        $this->openQuote = '"';
        $this->closeQuote = '"';
    }

    /**
     * PostgreSQL uses constraints for PRIMARY and UNIQUE in table definitions
     */
    public function compileIndexDefinition(IndexDefinition $indexDef): string
    {
        return match ($indexDef->getType()) {
            'PRIMARY' => "PRIMARY KEY (\"{$this->getColumnsList($indexDef)}\")",
            'UNIQUE' => "CONSTRAINT \"{$indexDef->getName()}\" UNIQUE (\"{$this->getColumnsList($indexDef)}\")",
            default => '',
        };
    }

    /**
     * @return list<string>
     */
    public function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $statements = [];

        if ($type === 'PRIMARY') {
            $cols = $this->getColumnsList($indexDef);
            $statements[] = "ALTER TABLE \"{$table}\" ADD PRIMARY KEY (\"{$cols}\")";
        } elseif ($type === 'UNIQUE') {
            $cols = $this->getColumnsList($indexDef);
            $statements[] = "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$indexDef->getName()}\" UNIQUE (\"{$cols}\")";
        } elseif ($type === 'FULLTEXT') {
            $statements = array_merge($statements, $this->compileFulltextIndexStatements($table, $indexDef));
        } elseif ($type === 'SPATIAL') {
            $statements = array_merge($statements, $this->compileSpatialIndexStatements($table, $indexDef));
        } elseif ($type === 'VECTOR') {
            $statements = array_merge($statements, $this->compileVectorIndexStatements($table, $indexDef));
        } elseif ($type === 'INDEX') {
            $cols = $this->getColumnsList($indexDef);
            $sql = "CREATE INDEX IF NOT EXISTS \"{$indexDef->getName()}\" ON \"{$table}\" (\"{$cols}\")";

            $algorithm = $indexDef->getAlgorithm();
            if ($algorithm !== null) {
                $algo = strtoupper($algorithm);
                if (\in_array($algo, ['BTREE', 'HASH', 'GIST', 'GIN', 'BRIN'], true)) {
                    $sql .= " USING {$algo}";
                }
            }
            $statements[] = $sql;
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    protected function compileFulltextIndexStatements(string $table, IndexDefinition $indexDef): array
    {
        $cols = implode(" || ' ' || ", array_map(fn ($c) => "\"{$c}\"", $indexDef->getColumns()));
        $name = $indexDef->getName();

        return ["CREATE INDEX IF NOT EXISTS \"{$name}\" ON \"{$table}\" USING gin(to_tsvector('english', {$cols}))"];
    }

    /**
     * @return list<string>
     */
    protected function compileSpatialIndexStatements(string $table, IndexDefinition $indexDef): array
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();
        $operatorClass = $indexDef->getOperatorClass() ?? 'gist';

        return ["CREATE INDEX IF NOT EXISTS \"{$name}\" ON \"{$table}\" USING {$operatorClass} (\"{$cols}\")"];
    }

    /**
     * @return list<string>
     */
    public function compileAddColumn(string $table, Column $column): array
    {
        $statements = [];
        $typeMapper = new PostgreSQLTypeMapper();

        $colDef = "\"{$column->getName()}\" " . $typeMapper->mapType($column->getType(), $column);
        if (! $column->isNullable()) {
            $colDef .= ' NOT NULL';
        }

        $statements[] = "ALTER TABLE \"{$table}\" ADD COLUMN IF NOT EXISTS {$colDef}";

        if ($column->hasDefault()) {
            $defaultCompiler = new PostgreSQLDefaultValueCompiler();
            $default = $defaultCompiler->compile($column->getDefault(), $column);
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT {$default}";
        } elseif ($column->shouldUseCurrent()) {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT CURRENT_TIMESTAMP";
        }

        $comment = $column->getComment();
        if ($comment !== null) {
            $escapedComment = addslashes($comment);
            $statements[] = "COMMENT ON COLUMN \"{$table}\".\"{$column->getName()}\" IS '{$escapedComment}'";
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    public function compileModifyColumn(string $table, Column $column): array
    {
        $statements = [];
        $typeMapper = new PostgreSQLTypeMapper();
        $columnName = $column->getName();
        $newType = $typeMapper->mapType($column->getType(), $column);

        $statements[] = "DO $$ BEGIN ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" DROP DEFAULT; EXCEPTION WHEN undefined_column THEN NULL; END $$";

        $using = "\"{$columnName}\"::{$newType}";
        $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" TYPE {$newType} USING {$using}";

        if (! $column->isNullable()) {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" SET NOT NULL";
        } else {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" DROP NOT NULL";
        }

        if ($column->hasDefault()) {
            $defaultCompiler = new PostgreSQLDefaultValueCompiler();
            $default = $defaultCompiler->compile($column->getDefault(), $column);
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" SET DEFAULT {$default}";
        } elseif ($column->shouldUseCurrent()) {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" SET DEFAULT CURRENT_TIMESTAMP";
        }

        return $statements;
    }

    /**
     * Add vector index support
     *
     * @return list<string>
     */
    protected function compileVectorIndexStatements(string $table, IndexDefinition $indexDef): array
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        $algorithm = $indexDef->getAlgorithm();
        $ops = match ($algorithm) {
            'L2', 'EUCLIDEAN' => 'vector_l2_ops',
            'IP', 'INNER_PRODUCT' => 'vector_ip_ops',
            'COSINE', null => 'vector_cosine_ops',
            default => 'vector_cosine_ops',
        };

        return [
            "CREATE INDEX IF NOT EXISTS \"{$name}\" ON \"{$table}\" USING ivfflat (\"{$cols}\" {$ops})",
        ];
    }
}
