<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\IndexDefinition;

/**
 * Base class for index compilation - defines the contract
 */
class IndexCompiler
{
    protected string $columnDelimiter = '`, `';

    protected string $indexTypeDelimiter = '';

    protected string $openQuote = '`';

    protected string $closeQuote = '`';

    public function compileIndexDefinition(IndexDefinition $indexDef): string
    {
        return match ($indexDef->getType()) {
            'PRIMARY' => $this->compilePrimaryIndex($indexDef),
            'UNIQUE' => $this->compileUniqueIndex($indexDef),
            'FULLTEXT' => $this->compileFulltextIndex($indexDef),
            'SPATIAL' => $this->compileSpatialIndex($indexDef),
            'RAW' => $this->compileRawIndex($indexDef),
            default => $this->compileRegularIndex($indexDef),
        };
    }

    protected function getColumnsList(IndexDefinition $indexDef): string
    {
        return implode($this->columnDelimiter, $indexDef->getColumns());
    }

    protected function quoteName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        return $this->openQuote.$name.$this->closeQuote;
    }

    protected function compilePrimaryIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);

        return "PRIMARY KEY ({$this->openQuote}{$cols}{$this->closeQuote})";
    }

    protected function compileUniqueIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        return "UNIQUE KEY {$this->quoteName($name)} ({$this->openQuote}{$cols}{$this->closeQuote})";
    }

    protected function compileRegularIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        return "KEY {$this->quoteName($name)} ({$this->openQuote}{$cols}{$this->closeQuote})";
    }

    protected function compileFulltextIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        return "FULLTEXT KEY {$this->quoteName($name)} ({$this->openQuote}{$cols}{$this->closeQuote})";
    }

    protected function compileSpatialIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        return "SPATIAL KEY {$this->quoteName($name)} ({$this->openQuote}{$cols}{$this->closeQuote})";
    }

    protected function compileRawIndex(IndexDefinition $indexDef): string
    {
        return $indexDef->getColumns()[0] ?? '';
    }
}
