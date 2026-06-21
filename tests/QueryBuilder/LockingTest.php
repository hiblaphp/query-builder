<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\Interfaces\TransactionalQueryBuilderInterface;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('lockForUpdate executes safely inside a transaction', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(newQb()->transaction(function (TransactionalQueryBuilderInterface $tx) {
        return await($tx->from('users')
            ->where('name', 'Alice')
            ->lockForUpdate()
            ->first());
    }));

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Alice')
    ;
});

test('lockForShare executes safely inside a transaction', function () {
    TestSchema::insertUsers(client(), [['name' => 'Bob', 'email' => 'b@test.com']]);

    $result = await(newQb()->transaction(function (TransactionalQueryBuilderInterface $tx) {
        return await($tx->from('users')
            ->where('name', 'Bob')
            ->lockForShare()
            ->first());
    }));

    expect($result)->not->toBeNull();
});

test('lockForUpdate with noWait executes safely', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(newQb()->transaction(function (TransactionalQueryBuilderInterface $tx) {
        return await($tx->from('users')
            ->lockForUpdate()
            ->noWait()
            ->first());
    }));

    expect($result)->not->toBeNull();
});

test('lockForUpdate with skipLocked executes safely', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(newQb()->transaction(function (TransactionalQueryBuilderInterface $tx) {
        return await($tx->from('users')
            ->lockForUpdate()
            ->skipLocked()
            ->first());
    }));

    expect($result)->not->toBeNull();
});

test('lockForShare with noWait executes safely', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(newQb()->transaction(function (TransactionalQueryBuilderInterface $tx) {
        return await($tx->from('users')
            ->lockForShare()
            ->noWait()
            ->first());
    }));

    expect($result)->not->toBeNull();
});

test('lockForShare with skipLocked executes safely', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(newQb()->transaction(function (TransactionalQueryBuilderInterface $tx) {
        return await($tx->from('users')
            ->lockForShare()
            ->skipLocked()
            ->first());
    }));

    expect($result)->not->toBeNull();
});

test('lockOf executes safely (ignored gracefully on MySQL, applies on PgSQL)', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(newQb()->transaction(function (TransactionalQueryBuilderInterface $tx) {
        return await($tx->from('users')
            ->lockForUpdate()
            ->lockOf('users')
            ->first());
    }));

    expect($result)->not->toBeNull();
});

test('withoutLock removes lock modes from builder instance', function () {
    $qb = qb('users')->lockForUpdate();

    if (ClientFactory::driverEnum() !== DatabaseDriver::Sqlite) {
        expect($qb->toSql())->toContain('FOR UPDATE');
    }

    $qbUnlocked = $qb->withoutLock();
    expect($qbUnlocked->toSql())->not->toContain('FOR UPDATE');
});

test('noWait without setting lock mode first throws LogicException', function () {
    expect(fn () => qb('users')->noWait())
        ->toThrow(LogicException::class, 'Cannot add NOWAIT modifier without a lock mode')
    ;
});

test('skipLocked without setting lock mode first throws LogicException', function () {
    expect(fn () => qb('users')->skipLocked())
        ->toThrow(LogicException::class, 'Cannot add SKIP LOCKED modifier without a lock mode')
    ;
});

test('lockOf without setting lock mode first throws LogicException', function () {
    expect(fn () => qb('users')->lockOf('users'))
        ->toThrow(LogicException::class, 'Cannot add OF clause without a lock mode')
    ;
});
