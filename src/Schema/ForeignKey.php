<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

class ForeignKey
{
    private string $name;

    /**
     * @var list<string>
     */
    private array $columns;

    private ?string $referenceTable = null;

    /**
     * @var list<string>
     */
    private array $referenceColumns = [];

    private string $onDelete = 'RESTRICT';

    private string $onUpdate = 'RESTRICT';

    private string $blueprintTable;

    /**
     * Create a new foreign key constraint instance.
     *
     * @param string $name The name of the foreign key constraint.
     * @param list<string> $columns The columns on the current table.
     * @param string $blueprintTable The name of the table this blueprint is for.
     */
    public function __construct(string $name, array $columns, string $blueprintTable = '')
    {
        $this->name = $name;
        $this->columns = $columns;
        $this->blueprintTable = $blueprintTable;
    }

    /**
     * Get the name of the foreign key constraint.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the columns on the current table.
     *
     * @return list<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the table the foreign key references.
     */
    public function getReferenceTable(): ?string
    {
        return $this->referenceTable;
    }

    /**
     * Get the columns the foreign key references.
     *
     * @return list<string>
     */
    public function getReferenceColumns(): array
    {
        return $this->referenceColumns;
    }

    /**
     * Get the "on delete" action for the foreign key.
     */
    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    /**
     * Get the "on update" action for the foreign key.
     */
    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    /**
     * Get the name of the table this blueprint is for.
     */
    public function getBlueprintTable(): string
    {
        return $this->blueprintTable;
    }

    /**
     * Specify the referenced column(s) on the foreign table.
     *
     * @param string|list<string> $columns
     */
    public function references(string|array $columns): self
    {
        $this->referenceColumns = \is_array($columns) ? $columns : [$columns];

        return $this;
    }

    /**
     * Specify the referenced table.
     */
    public function on(string $table): self
    {
        $this->referenceTable = $table;

        return $this;
    }

    /**
     * Specify the action to take on deletion.
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);

        return $this;
    }

    /**
     * Specify the action to take on update.
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);

        return $this;
    }

    /**
     * Set the "on delete" action to "CASCADE".
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    /**
     * Set the "on update" action to "CASCADE".
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('CASCADE');
    }

    /**
     * Set the "on delete" action to "SET NULL".
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    /**
     * Set the "on delete" action to "RESTRICT".
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('RESTRICT');
    }

    /**
     * Set the "on update" action to "RESTRICT".
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('RESTRICT');
    }

    /**
     * Set the "on delete" action to "NO ACTION".
     */
    public function noActionOnDelete(): self
    {
        return $this->onDelete('NO ACTION');
    }

    /**
     * Set the "on update" action to "NO ACTION".
     */
    public function noActionOnUpdate(): self
    {
        return $this->onUpdate('NO ACTION');
    }
}
