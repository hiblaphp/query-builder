<?php

declare(strict_types=1);

use Hibla\Sql\Exceptions\ConstraintViolationException;
use Hibla\Sql\Exceptions\PreparedException;
use Hibla\Sql\Exceptions\QueryException;
use Hibla\Sql\Exceptions\TransactionException;
use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('insertBatch returns 0 if data array is empty', function () {
    $affected = await(qb('users')->insertBatch([]));
    expect($affected)->toBe(0);
});

test('upsert returns 0 if data array is empty', function () {
    $affected = await(qb('users')->upsert([], 'email'));
    expect($affected)->toBe(0);
});

test('whereIn with empty array returns empty results safely', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(qb('users')->whereIn('id', [])->get());
    expect($result)->toBeEmpty();
});

test('whereNotIn with empty array returns all results safely', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(qb('users')->whereNotIn('id', [])->get());
    expect($result)->toHaveCount(1);
});

test('whereBetween throws InvalidArgumentException if array does not contain exactly 2 elements', function () {
    expect(fn () => qb('users')->whereBetween('age', [18]))
        ->toThrow(InvalidArgumentException::class, 'whereBetween requires exactly 2 values')
    ;
});

test('throws QueryException on invalid SQL syntax', function () {
    expect(fn () => await(qb('users')->selectRaw('INVALID_FUNCTION()')->get()))
        ->toThrow(QueryException::class)
    ;
});

test('throws ConstraintViolationException on duplicate key insert', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'alice@test.com']]);

    expect(fn () => await(qb('users')->insert([
        'name' => 'Duplicate',
        'email' => 'alice@test.com',
    ])))->toThrow(ConstraintViolationException::class);
});

test('AST throws InvalidArgumentException when passing named bindings to whereRaw', function () {
    expect(fn () => await(qb('users')->whereRaw('email = :email', ['email' => 'a@test.com'])->get()))
        ->toThrow(InvalidArgumentException::class, 'Query builder primitives only support positional bindings')
    ;
});

test('QueryBuilder raw() throws PreparedException if named parameter is missing value', function () {
    expect(fn () => await(qb('users')->raw('SELECT * FROM users WHERE email = :email', ['wrong_key' => 'test'])))
        ->toThrow(
            PreparedException::class,
            'Missing value for named parameter: :email'
        )
    ;
});

test('QueryBuilder raw() throws PreparedException if mixing named and positional parameters', function () {
    expect(fn () => await(qb('users')->raw('SELECT * FROM users WHERE id = ? OR email = :email', [1, 'email' => 'a@test.com'])))
        ->toThrow(
            PreparedException::class,
            'Cannot mix named and positional parameters in the same query.'
        )
    ;
});

test('cursor pagination ignores invalid base64 cursor and defaults to page 1', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $_GET['cursor'] = 'invalid_not_base64_!@#$';
    $paginator = await(qb('users')->cursorPaginate(10, 'id', 'http://localhost/users'));
    expect($paginator->items)->toHaveCount(1);

    unset($_GET['cursor']);
});

test('value() returns null when column does not exist or table is empty', function () {
    $val = await(qb('users')->value('name'));
    expect($val)->toBeNull();

    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    expect(fn () => await(qb('users')->value('nonexistent_col')))
        ->toThrow(QueryException::class)
    ;
});

test('calling commit on a transaction that is already closed throws TransactionException', function () {
    $tx = await(newQb()->beginTransaction());
    await($tx->commit());

    expect(fn () => await($tx->commit()))
        ->toThrow(TransactionException::class, 'Cannot perform operation: transaction is no longer active')
    ;
});