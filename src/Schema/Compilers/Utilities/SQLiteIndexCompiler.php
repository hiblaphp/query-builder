<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Exceptions\SchemaCompilerException;
use Hibla\QueryBuilder\Schema\Blueprint;
use Hibla\QueryBuilder\Schema\Column;
use Hibla\QueryBuilder\Schema\ForeignKey;
use Hibla\QueryBuilder\Schema\IndexDefinition;
use Hibla\QueryBuilder\Schema\SchemaCompiler;

class SQLiteIndexCompiler extends IndexCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '`, `';
        $this->openQuote = '`';
        $this->closeQuote = '`';
    }

    /**
     * @param array<int, IndexDefinition> $indexDefs
     *
     * @return array<int, string>
     */
    public function compileAddIndexDefinition(string $table, array $indexDefs): array
    {
        $statements = [];
        foreach ($indexDefs as $indexDef) {
            if ($indexDef->getType() === 'PRIMARY') {
                continue;
            }

            $type = $indexDef->getType();
            $cols = $this->getColumnsList($indexDef);

            if ($type === 'UNIQUE') {
                $statements[] = "CREATE UNIQUE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
            } elseif (\in_array($type, ['INDEX', 'FULLTEXT', 'SPATIAL'], true)) {
                $statements[] = "CREATE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
            }
        }

        return $statements;
    }

    /**
     * @param array<int, array<string, mixed>> $existingTableColumns
     *
     * @return array<int, string>
     */
    public function compileTableRecreation(Blueprint $blueprint, array $existingTableColumns, SchemaCompiler $compiler): array
    {
        $table = $blueprint->getTable();
        $tempTable = "temp_{$table}_" . bin2hex(random_bytes(4));

        $statements = [];
        $statements[] = 'PRAGMA foreign_keys=OFF';

        $newBlueprint = $this->buildNewBlueprint($blueprint, $tempTable, $existingTableColumns);
        $statements[] = $compiler->compileCreate($newBlueprint);

        $transferInfo = $this->getTransferColumns($blueprint, $existingTableColumns);
        if ($transferInfo['old'] !== [] && $transferInfo['new'] !== []) {
            $oldCols = implode('`, `', $transferInfo['old']);
            $newCols = implode('`, `', $transferInfo['new']);
            $statements[] = "INSERT INTO `{$tempTable}` (`{$newCols}`) SELECT `{$oldCols}` FROM `{$table}`";
        }

        $statements[] = "DROP TABLE `{$table}`";
        $statements[] = "ALTER TABLE `{$tempTable}` RENAME TO `{$table}`";

        $dropIndexNames = array_map(fn($idx) => $idx[0], $blueprint->getDropIndexes());
        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            $indexName = $indexDef->getName();
            if ($indexDef->getType() !== 'PRIMARY' && ! \in_array($indexName, $dropIndexNames, true)) {
                $indexStatements = $this->compileAddIndexDefinition($table, [$indexDef]);
                $statements = array_merge($statements, $indexStatements);
            }
        }

        $statements[] = 'PRAGMA foreign_key_check';
        $statements[] = 'PRAGMA foreign_keys=ON';

        return $statements;
    }

    /**
     * @param array<int, array<string, mixed>> $existingTableColumns
     */
    private function buildNewBlueprint(Blueprint $originalBlueprint, string $newTableName, array $existingTableColumns): Blueprint
    {
        $newBlueprint = new Blueprint($newTableName);

        $dropColumns = $originalBlueprint->getDropColumns();
        $renameMap = $this->getRenameMap($originalBlueprint->getRenameColumns());
        $modifyMap = $this->getModifyMap($originalBlueprint->getModifyColumns());

        foreach ($existingTableColumns as $existingCol) {
            if (! isset($existingCol['name']) || ! \is_string($existingCol['name'])) {
                continue;
            }

            $columnName = $existingCol['name'];

            if (\in_array($columnName, $dropColumns, true)) {
                continue;
            }

            if (isset($renameMap[$columnName])) {
                $column = $this->createColumnFromPragma($existingCol);
                $newColumn = $column->copyWithName($renameMap[$columnName]);
                $newColumn->setBlueprint($newBlueprint);
                $this->addColumnToBlueprint($newBlueprint, $newColumn);

                continue;
            }

            if (isset($modifyMap[$columnName])) {
                $modifiedColumn = $modifyMap[$columnName];
                $modifiedColumn->setBlueprint($newBlueprint);
                $this->addColumnToBlueprint($newBlueprint, $modifiedColumn);

                continue;
            }

            $column = $this->createColumnFromPragma($existingCol);
            $column->setBlueprint($newBlueprint);
            $this->addColumnToBlueprint($newBlueprint, $column);
        }

        foreach ($originalBlueprint->getColumns() as $column) {
            $clonedColumn = clone $column;
            $clonedColumn->setBlueprint($newBlueprint);
            $this->addColumnToBlueprint($newBlueprint, $clonedColumn);
        }

        $dropIndexNames = array_map(fn($idx) => $idx[0], $originalBlueprint->getDropIndexes());
        foreach ($originalBlueprint->getIndexDefinitions() as $indexDef) {
            $indexName = $indexDef->getName() ?? 'PRIMARY';
            if (! \in_array($indexName, $dropIndexNames, true)) {
                $this->addIndexDefinitionToBlueprint($newBlueprint, $indexDef);
            }
        }

        $dropForeignKeys = $originalBlueprint->getDropForeignKeys();
        foreach ($originalBlueprint->getForeignKeys() as $foreignKey) {
            if (! \in_array($foreignKey->getName(), $dropForeignKeys, true)) {
                $this->addForeignKeyToBlueprint($newBlueprint, $foreignKey);
            }
        }

        return $newBlueprint;
    }

    /**
     * @param array<string, mixed> $pragmaRow
     */
    private function createColumnFromPragma(array $pragmaRow): Column
    {
        $name = $pragmaRow['name'] ?? '';
        $type = $pragmaRow['type'] ?? 'TEXT';

        if (! \is_string($name) || ! \is_string($type)) {
            throw new SchemaCompilerException('Invalid pragma row data from SQLite');
        }

        $column = new Column($name, $this->mapSqliteTypeToGeneric($type));

        $notnull = $pragmaRow['notnull'] ?? 1;
        if (\is_int($notnull) && $notnull === 0) {
            $column->nullable();
        }

        if (isset($pragmaRow['dflt_value']) && \is_string($pragmaRow['dflt_value'])) {
            $column->default($this->parseDefaultValue($pragmaRow['dflt_value']));
        }

        $pk = $pragmaRow['pk'] ?? 0;
        if (\is_int($pk) && $pk === 1) {
            $column->primary();
            if (stripos($type, 'INTEGER') !== false) {
                $column->autoIncrement();
            }
        }

        return $column;
    }

    private function mapSqliteTypeToGeneric(string $sqliteType): string
    {
        $sqliteType = strtoupper($sqliteType);

        if (str_contains($sqliteType, 'INT')) {
            return 'INTEGER';
        } elseif (str_contains($sqliteType, 'CHAR') || str_contains($sqliteType, 'TEXT')) {
            return 'TEXT';
        } elseif (str_contains($sqliteType, 'REAL') || str_contains($sqliteType, 'FLOA') || str_contains($sqliteType, 'DOUB')) {
            return 'REAL';
        }

        return 'TEXT';
    }

    private function parseDefaultValue(string $value): mixed
    {
        if (preg_match("/^'(.*)'$/", $value, $matches) === 1) {
            return $matches[1];
        }

        if (is_numeric($value)) {
            $pos = strpos($value, '.');

            return ($pos !== false) ? (float) $value : (int) $value;
        }

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        return $value;
    }

    /**
     * @param array<int, array<string, mixed>> $existingTableColumns
     *
     * @return array<string, array<int, string>>
     */
    private function getTransferColumns(Blueprint $blueprint, array $existingTableColumns): array
    {
        $oldColumns = [];
        $newColumns = [];
        $dropColumns = $blueprint->getDropColumns();
        $renameMap = $this->getRenameMap($blueprint->getRenameColumns());

        foreach ($existingTableColumns as $existingCol) {
            if (! isset($existingCol['name']) || ! \is_string($existingCol['name'])) {
                continue;
            }

            $columnName = $existingCol['name'];

            if (\in_array($columnName, $dropColumns, true)) {
                continue;
            }

            $oldColumns[] = $columnName;
            $newColumns[] = $renameMap[$columnName] ?? $columnName;
        }

        return ['old' => $oldColumns, 'new' => $newColumns];
    }

    /**
     * @param array<int, array<string, string>> $renameColumns
     *
     * @return array<string, string>
     */
    private function getRenameMap(array $renameColumns): array
    {
        $map = [];
        foreach ($renameColumns as $rename) {
            if (isset($rename['from'], $rename['to']) && \is_string($rename['from']) && \is_string($rename['to'])) {
                $map[$rename['from']] = $rename['to'];
            }
        }

        return $map;
    }

    /**
     * @param array<int, Column> $modifyColumns
     *
     * @return array<string, Column>
     */
    private function getModifyMap(array $modifyColumns): array
    {
        $map = [];
        foreach ($modifyColumns as $column) {
            $map[$column->getName()] = $column;
        }

        return $map;
    }

    private function addColumnToBlueprint(Blueprint $blueprint, Column $column): void
    {
        $blueprint->addColumn($column);
    }

    private function addIndexDefinitionToBlueprint(Blueprint $blueprint, IndexDefinition $indexDef): void
    {
        $blueprint->addIndexDefinition($indexDef);
    }

    private function addForeignKeyToBlueprint(Blueprint $blueprint, ForeignKey $foreignKey): void
    {
        $blueprint->addForeignKey($foreignKey);
    }
}
