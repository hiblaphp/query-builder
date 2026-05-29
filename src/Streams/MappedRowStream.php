<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Streams;

use Hibla\Sql\RowStream;

/**
 * Decorates a RowStream to map its yielded rows (e.g. casting arrays to objects).
 */
class MappedRowStream implements RowStream
{
    /**
     * @var callable(array<string, mixed>): object
     */
    private $mapper;

    /**
     * @inheritDoc
     */
    public int $columnCount {
        get => $this->innerStream->columnCount;
    }

    /**
     * @inheritDoc
     */
    public array $columns {
        get => $this->innerStream->columns;
    }

    /**
     * @param RowStream $innerStream
     * @param callable(array<string, mixed>): object $mapper
     */
    public function __construct(
        private readonly RowStream $innerStream,
        callable $mapper
    ) {
        $this->mapper = $mapper;
    }

    /**
     * @inheritDoc
     */
    public function cancel(): void
    {
        $this->innerStream->cancel();
    }

    /**
     * @inheritDoc
     */
    public function isCancelled(): bool
    {
        return $this->innerStream->isCancelled();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Generator
    {
        foreach ($this->innerStream as $row) {
            // @phpstan-ignore-next-line
            yield ($this->mapper)($row);
        }
    }
}
