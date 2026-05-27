<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

/**
 * @phpstan-type TIndexDefinitionArray array{
 *     type: string,
 *     name: string|null,
 *     columns: list<string>,
 *     algorithm: string|null,
 *     operatorClass: string|null,
 *     with: string|null,
 *     using: array<string, mixed>
 * }
 */
class IndexDefinition
{
    private string $type;

    /**
     * @var list<string>
     */
    private array $columns;

    private ?string $name;

    private ?string $algorithm = null;

    private ?string $operatorClass = null;

    private ?string $with = null;

    /**
     * @var array<string, mixed>
     */
    private array $using = [];

    /**
     * Create a new index definition instance.
     *
     * @param string $type The type of index (e.g., 'PRIMARY', 'UNIQUE').
     * @param list<string> $columns The columns included in the index.
     * @param string|null $name The optional name of the index.
     */
    public function __construct(string $type, array $columns, ?string $name = null)
    {
        $this->type = $type;
        $this->columns = $columns;
        $this->name = $name;
    }

    /**
     * Get the type of the index.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the columns for the index.
     *
     * @return list<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the name of the index.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the algorithm for the index.
     */
    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    /**
     * Get the operator class for the index.
     */
    public function getOperatorClass(): ?string
    {
        return $this->operatorClass;
    }

    /**
     * Get the WITH clause parameters.
     */
    public function getWith(): ?string
    {
        return $this->with;
    }

    /**
     * Get the USING clause parameters.
     *
     * @return array<string, mixed>
     */
    public function getUsing(): array
    {
        return $this->using;
    }

    /**
     * Specify the algorithm for the index (e.g., 'BTREE', 'HASH').
     */
    public function algorithm(string $algorithm): self
    {
        $this->algorithm = strtoupper($algorithm);

        return $this;
    }

    /**
     * Specify the operator class for spatial indexes.
     */
    public function operatorClass(string $operatorClass): self
    {
        $this->operatorClass = $operatorClass;

        return $this;
    }

    /**
     * Specify WITH clause parameters for PostgreSQL.
     */
    public function with(string $with): self
    {
        $this->with = $with;

        return $this;
    }

    /**
     * Add USING clause parameters.
     *
     * @param array<string, mixed> $params
     */
    public function using(array $params): self
    {
        $this->using = array_merge($this->using, $params);

        return $this;
    }

    /**
     * Convert the index definition to an array representation.
     *
     * @return TIndexDefinitionArray
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'columns' => $this->columns,
            'algorithm' => $this->algorithm,
            'operatorClass' => $this->operatorClass,
            'with' => $this->with,
            'using' => $this->using,
        ];
    }
}
