<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema;

use Carbon\Carbon;
use Doctrine\Inflector\Inflector as DoctrineInflector;
use Doctrine\Inflector\InflectorFactory;
use Rcalicdan\ConfigLoader\Config;

/**
 * @phpstan-type TColumnIndex array{type: 'UNIQUE'|'INDEX'|'FULLTEXT'|'SPATIAL'|'VECTOR', name: string|null, algorithm: string|null, operatorClass?: string|null}
 * @phpstan-type TColumnArray array{
 *     name: string,
 *     type: string,
 *     length: int|null,
 *     precision: int|null,
 *     scale: int|null,
 *     nullable: bool,
 *     default: mixed,
 *     hasDefault: bool,
 *     unsigned: bool,
 *     autoIncrement: bool,
 *     primary: bool,
 *     unique: bool,
 *     comment: string|null,
 *     after: string|null,
 *     useCurrent: bool,
 *     useCurrentOnUpdate: bool,
 *     onUpdate: string|null,
 *     enumValues: list<string>,
 *     hasForeignKey: bool,
 *     columnIndexes: list<TColumnIndex>,
 *     timezone: string|null
 * }
 */
class Column
{
    private string $name;

    private string $type;

    private ?int $length;

    private ?int $precision;

    private ?int $scale;

    private bool $nullable = false;

    private mixed $default = null;

    private bool $hasDefault = false;

    private bool $unsigned = false;

    private bool $autoIncrement = false;

    private bool $primary = false;

    private bool $unique = false;

    private ?string $comment = null;

    private ?string $after = null;

    private bool $useCurrent = false;

    private bool $useCurrentOnUpdate = false;

    private ?string $onUpdate = null;

    /**
     * @var list<string>
     */
    private array $enumValues = [];

    private ?ForeignKey $foreignKey = null;

    private ?Blueprint $blueprint = null;

    private static ?DoctrineInflector $inflector = null;

    /**
     * @var list<TColumnIndex>
     */
    private array $columnIndexes = [];

    private ?string $timezone = null;

    public function __construct(
        string $name,
        string $type,
        ?int $length = null,
        ?int $precision = null,
        ?int $scale = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->length = $length;
        $this->precision = $precision;
        $this->scale = $scale;

        if (\in_array($type, ['TIMESTAMP', 'DATETIME', 'DATE'], true)) {
            $config = Config::loadFromRoot('hibla-migrations');
            $this->timezone = (\is_array($config) && isset($config['timezone']) && \is_string($config['timezone']))
                ? $config['timezone']
                : 'UTC';
        }
    }

    /**
     * Get the singleton inflector instance.
     */
    private static function getInflector(): DoctrineInflector
    {
        if (self::$inflector === null) {
            self::$inflector = InflectorFactory::create()->build();
        }

        return self::$inflector;
    }

    /**
     * Clone the column instance.
     */
    public function __clone()
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey = clone $this->foreignKey;
        }

        $this->blueprint = null;
    }

    /**
     * Get the column name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the column name.
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the column type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the column type.
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the column length.
     */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * Set the column length.
     */
    public function setLength(?int $length): self
    {
        $this->length = $length;

        return $this;
    }

    /**
     * Get the column precision.
     */
    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    /**
     * Set the column precision.
     */
    public function setPrecision(?int $precision): self
    {
        $this->precision = $precision;

        return $this;
    }

    /**
     * Get the column scale.
     */
    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * Set the column scale.
     */
    public function setScale(?int $scale): self
    {
        $this->scale = $scale;

        return $this;
    }

    /**
     * Check if the column is nullable.
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Get the default value of the column.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Check if the column has a default value.
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Check if the column is unsigned.
     */
    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    /**
     * Check if the column has auto-increment enabled.
     */
    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    /**
     * Check if the column is a primary key.
     */
    public function isPrimary(): bool
    {
        return $this->primary;
    }

    /**
     * Check if the column has a unique constraint.
     */
    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * Get the column comment.
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Get the column that this column should be placed "after".
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }

    /**
     * Check if the column should use the "current" timestamp.
     */
    public function shouldUseCurrent(): bool
    {
        return $this->useCurrent;
    }

    /**
     * Check if the column should use current timestamp on update.
     */
    public function shouldUseCurrentOnUpdate(): bool
    {
        return $this->useCurrentOnUpdate;
    }

    /**
     * Get the "on update" action for the column.
     */
    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }

    /**
     * Get the enum values for the column.
     *
     * @return list<string>
     */
    public function getEnumValues(): array
    {
        return $this->enumValues;
    }

    /**
     * Get the foreign key constraint for the column.
     */
    public function getForeignKey(): ?ForeignKey
    {
        return $this->foreignKey;
    }

    /**
     * Get the blueprint instance for the column.
     */
    public function getBlueprint(): ?Blueprint
    {
        return $this->blueprint;
    }

    /**
     * Get the indexes for the column.
     *
     * @return list<TColumnIndex>
     */
    public function getColumnIndexes(): array
    {
        return $this->columnIndexes;
    }

    /**
     * Get the timezone for this column.
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Allow the column to be nullable.
     */
    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    /**
     * Set the default value for the column.
     */
    public function default(mixed $value): self
    {
        // If value is a Carbon instance, convert to appropriate format
        if ($value instanceof Carbon) {
            $value = $value->setTimezone($this->timezone ?? 'UTC')->toDateTimeString();
        }

        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    /**
     * Set the column as unsigned.
     */
    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;

        return $this;
    }

    /**
     * Set the column to auto-increment.
     */
    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;

        return $this;
    }

    /**
     * Set the column as a primary key.
     */
    public function primary(bool $value = true): self
    {
        $this->primary = $value;

        return $this;
    }

    /**
     * Add a unique constraint to the column.
     */
    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        if ($value) {
            $this->columnIndexes[] = [
                'type' => 'UNIQUE',
                'name' => null,
                'algorithm' => null,
            ];
        }

        return $this;
    }

    /**
     * Add a comment to the column.
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Specify the column that this column should be placed "after".
     */
    public function after(string $column): self
    {
        $this->after = $column;

        return $this;
    }

    /**
     * Set the column to use the current timestamp.
     */
    public function useCurrent(bool $value = true): self
    {
        $this->useCurrent = $value;

        return $this;
    }

    /**
     * Set the column to use current timestamp on update.
     */
    public function useCurrentOnUpdate(bool $value = true): self
    {
        $this->useCurrentOnUpdate = $value;
        if ($value) {
            $this->onUpdate = 'CURRENT_TIMESTAMP';
        }

        return $this;
    }

    /**
     * Specify an "on update" action for the column.
     */
    public function onUpdate(string $value): self
    {
        $this->onUpdate = $value;

        return $this;
    }

    /**
     * Set the timezone for this column.
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Set the enum values for the column.
     *
     * @param list<string> $values
     */
    public function setEnumValues(array $values): self
    {
        $this->enumValues = $values;

        return $this;
    }

    /**
     * Set the blueprint instance for the column.
     */
    public function setBlueprint(Blueprint $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    /**
     * Add a regular index to the column.
     */
    public function index(?string $name = null, ?string $algorithm = null): self
    {
        $this->columnIndexes[] = [
            'type' => 'INDEX',
            'name' => $name,
            'algorithm' => $algorithm,
        ];

        return $this;
    }

    /**
     * Add a full-text index to the column.
     */
    public function fullText(?string $name = null, ?string $algorithm = null): self
    {
        $this->columnIndexes[] = [
            'type' => 'FULLTEXT',
            'name' => $name,
            'algorithm' => $algorithm,
        ];

        return $this;
    }

    /**
     * Add a spatial index to the column.
     */
    public function spatialIndex(?string $name = null, ?string $operatorClass = null): self
    {
        $this->columnIndexes[] = [
            'type' => 'SPATIAL',
            'name' => $name,
            'algorithm' => null,
            'operatorClass' => $operatorClass,
        ];

        return $this;
    }

    /**
     * Add a foreign key constraint to the column.
     */
    public function constrained(?string $table = null, string $column = 'id'): self
    {
        if ($this->blueprint === null) {
            throw new \RuntimeException('Blueprint reference not set on column');
        }

        if ($table === null) {
            $table = $this->guessTableName();
        }

        $foreignKey = $this->blueprint->foreign($this->name);
        $foreignKey->references($column)->on($table);

        $this->foreignKey = $foreignKey;

        return $this;
    }

    /**
     * Guess the table name for a foreign key constraint.
     */
    private function guessTableName(): string
    {
        $name = $this->name;

        if (str_ends_with($name, '_id')) {
            $name = substr($name, 0, -3);
        }

        return self::getInflector()->pluralize($name);
    }

    /**
     * Add a "cascade on delete" action to the foreign key.
     */
    public function cascadeOnDelete(): self
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey->cascadeOnDelete();
        }

        return $this;
    }

    /**
     * Add a "cascade on update" action to the foreign key.
     */
    public function cascadeOnUpdate(): self
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey->cascadeOnUpdate();
        }

        return $this;
    }

    /**
     * Add a "set null on delete" action to the foreign key.
     */
    public function nullOnDelete(): self
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey->nullOnDelete();
        }

        return $this;
    }

    /**
     * Add a "restrict on delete" action to the foreign key.
     */
    public function restrictOnDelete(): self
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey->onDelete('RESTRICT');
        }

        return $this;
    }

    /**
     * Add a "restrict on update" action to the foreign key.
     */
    public function restrictOnUpdate(): self
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey->onUpdate('RESTRICT');
        }

        return $this;
    }

    /**
     * Add a "no action on delete" action to the foreign key.
     */
    public function noActionOnDelete(): self
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey->onDelete('NO ACTION');
        }

        return $this;
    }

    /**
     * Add a "no action on update" action to the foreign key.
     */
    public function noActionOnUpdate(): self
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey->onUpdate('NO ACTION');
        }

        return $this;
    }

    /**
     * Create a new column instance from this column with a different name.
     */
    public function copyWithName(string $newName): self
    {
        $column = clone $this;
        $column->setName($newName);

        return $column;
    }

    /**
     * Add a vector index to the column (PostgreSQL only).
     */
    public function vectorIndex(?string $name = null, ?string $distanceMetric = 'COSINE'): self
    {
        $this->columnIndexes[] = [
            'type' => 'VECTOR',
            'name' => $name,
            'algorithm' => $distanceMetric,
        ];

        return $this;
    }

    /**
     * Create a new column instance with modified attributes.
     *
     * @param array<string, mixed> $modifications
     */
    public function copyWithModifications(array $modifications): self
    {
        $column = clone $this;

        foreach ($modifications as $attribute => $value) {
            match ($attribute) {
                'type' => \is_string($value) ? $column->setType($value) : null,
                'length' => (\is_int($value) || $value === null) ? $column->setLength($value) : null,
                'precision' => (\is_int($value) || $value === null) ? $column->setPrecision($value) : null,
                'scale' => (\is_int($value) || $value === null) ? $column->setScale($value) : null,
                'nullable' => \is_bool($value) ? $column->nullable($value) : null,
                'default' => $column->default($value),
                'unsigned' => \is_bool($value) ? $column->unsigned($value) : null,
                'unique' => \is_bool($value) ? $column->unique($value) : null,
                'comment' => \is_string($value) ? $column->comment($value) : null,
                'timezone' => \is_string($value) ? $column->timezone($value) : null,
                default => null,
            };
        }

        return $column;
    }

    /**
     * Convert the column to an array representation.
     *
     * @return TColumnArray
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'hasDefault' => $this->hasDefault,
            'unsigned' => $this->unsigned,
            'autoIncrement' => $this->autoIncrement,
            'primary' => $this->primary,
            'unique' => $this->unique,
            'comment' => $this->comment,
            'after' => $this->after,
            'useCurrent' => $this->useCurrent,
            'useCurrentOnUpdate' => $this->useCurrentOnUpdate,
            'onUpdate' => $this->onUpdate,
            'enumValues' => $this->enumValues,
            'hasForeignKey' => $this->foreignKey !== null,
            'columnIndexes' => $this->columnIndexes,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * Create a column from an array representation.
     *
     * @param TColumnArray $data
     */
    public static function fromArray(array $data): self
    {
        $column = new self(
            $data['name'],
            $data['type'],
            $data['length'] ?? null,
            $data['precision'] ?? null,
            $data['scale'] ?? null
        );

        if ($data['nullable'] ?? false) {
            $column->nullable();
        }

        if ($data['hasDefault'] ?? false) {
            $column->default($data['default'] ?? null);
        }

        if ($data['unsigned'] ?? false) {
            $column->unsigned();
        }

        if ($data['autoIncrement'] ?? false) {
            $column->autoIncrement();
        }

        if ($data['primary'] ?? false) {
            $column->primary();
        }

        if ($data['unique'] ?? false) {
            $column->unique();
        }

        if (isset($data['comment'])) {
            $column->comment($data['comment']);
        }

        if (isset($data['after'])) {
            $column->after($data['after']);
        }

        if ($data['useCurrent'] ?? false) {
            $column->useCurrent();
        }

        if ($data['useCurrentOnUpdate'] ?? false) {
            $column->useCurrentOnUpdate();
        }

        if (isset($data['onUpdate'])) {
            $column->onUpdate($data['onUpdate']);
        }

        if (isset($data['enumValues']) && $data['enumValues'] !== []) {
            $column->setEnumValues($data['enumValues']);
        }

        if (isset($data['columnIndexes']) && $data['columnIndexes'] !== []) {
            $column->columnIndexes = $data['columnIndexes'];
        }

        if (isset($data['timezone'])) {
            $column->timezone($data['timezone']);
        }

        return $column;
    }
}
