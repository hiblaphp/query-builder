<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Sql\RowStream;

/**
 * Defines the contract for executing raw SQL queries directly.
 */
interface RawQueryInterface
{
    /**
     * Execute a raw query and return all rows.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<array<int, array<string, mixed>>|array<int, object>>
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw query and return the first result.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<array<string, mixed>|object|null>
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw query and return a single scalar value.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<mixed>
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE).
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw query and return an unbuffered stream of results.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     * @param positive-int $bufferSize Number of rows to buffer internally per read.
     *
     * @return PromiseInterface<RowStream>
     */
    public function rawStream(string $sql, array $bindings = [], int $bufferSize = 100): PromiseInterface;
}
