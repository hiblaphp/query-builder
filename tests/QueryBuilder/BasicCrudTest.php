<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('get returns empty array when table is empty', function () {
    $result = await(qb('users')->get());

    expect($result)->toBeArray()->toBeEmpty();
});

test('get returns all rows', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob',   'email' => 'bob@test.com'],
    ]);

    $result = await(qb('users')->get());

    expect($result)->toHaveCount(2);
});

test('get returns objects by default', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
    ]);

    $result = await(qb('users')->get());

    expect($result[0])->toBeObject();
});

test('toArray mode returns associative arrays instead of objects', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
    ]);

    $result = await(qb('users')->toArray()->get());

    expect($result[0])->toBeArray()->toHaveKey('name');
});

test('first returns null when table is empty', function () {
    expect(await(qb('users')->first()))->toBeNull();
});

test('first returns a single object', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob',   'email' => 'bob@test.com'],
    ]);

    $result = await(qb('users')->orderBy('name')->first());

    expect($result)->toBeObject()
        ->and($result->name)->toBe('Alice')
    ;
});

test('find returns the row matching the given id', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
    ]);

    $inserted = await(qb('users')->first());
    $found = await(qb('users')->find($inserted->id));

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Alice')
    ;
});

test('find returns null when id does not exist', function () {
    expect(await(qb('users')->find(99999)))->toBeNull();
});

test('findOrFail throws RecordNotFoundException when row is missing', function () {
    expect(fn () => await(qb('users')->findOrFail(99999)))
        ->toThrow(Hibla\QueryBuilder\Exceptions\RecordNotFoundException::class)
    ;
});

test('insert adds a single row', function () {
    await(qb('users')->insert([
        'name' => 'Carol',
        'email' => 'carol@test.com',
    ]));

    expect(await(qb('users')->count()))->toBe(1);
});

test('insertGetId returns the new primary key', function () {
    $id = await(qb('users')->insertGetId([
        'name' => 'Dave',
        'email' => 'dave@test.com',
    ]));

    expect($id)->toBeInt()->toBeGreaterThan(0);
});

test('insertBatch inserts multiple rows at once', function () {
    await(qb('users')->insertBatch([
        ['name' => 'Eve',   'email' => 'eve@test.com'],
        ['name' => 'Frank', 'email' => 'frank@test.com'],
        ['name' => 'Grace', 'email' => 'grace@test.com'],
    ]));

    expect(await(qb('users')->count()))->toBe(3);
});

test('update modifies only matched rows and returns affected count', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'bob@test.com',   'status' => 'active'],
    ]);

    $affected = await(qb('users')->where('email', 'alice@test.com')->update(['status' => 'inactive']));
    $alice = await(qb('users')->where('email', 'alice@test.com')->first());
    $bob = await(qb('users')->where('email', 'bob@test.com')->first());

    expect($affected)->toBe(1)
        ->and($alice->status)->toBe('inactive')
        ->and($bob->status)->toBe('active')
    ;
});

test('update returns zero when no rows match', function () {
    $affected = await(qb('users')->where('id', 99999)->update(['status' => 'inactive']));

    expect($affected)->toBe(0);
});

test('delete removes matched rows and returns affected count', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob',   'email' => 'bob@test.com'],
    ]);

    $affected = await(qb('users')->where('email', 'alice@test.com')->delete());

    expect($affected)->toBe(1)
        ->and(await(qb('users')->count()))->toBe(1)
    ;
});

test('delete returns zero when no rows match', function () {
    $affected = await(qb('users')->where('id', 99999)->delete());

    expect($affected)->toBe(0);
});

test('upsert inserts when there is no conflict', function () {
    await(qb('users')->upsert(
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        'email',
        ['status']
    ));

    expect(await(qb('users')->count()))->toBe(1);
});

test('upsert updates the conflicting row', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
    ]);

    await(qb('users')->upsert(
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'inactive'],
        'email',
        ['status']
    ));

    $user = await(qb('users')->where('email', 'alice@test.com')->first());

    expect(await(qb('users')->count()))->toBe(1)
        ->and($user->status)->toBe('inactive')
    ;
});

test('value returns a single column value from the first row', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
    ]);

    $name = await(qb('users')->orderBy('id')->value('name'));

    expect($name)->toBe('Alice');
});

test('pluck returns a flat array of column values', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob',   'email' => 'bob@test.com'],
    ]);

    $names = await(qb('users')->orderBy('name')->pluck('name'));

    expect($names)->toBe(['Alice', 'Bob']);
});

test('pluck with key returns a column-keyed map', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob',   'email' => 'bob@test.com'],
    ]);

    $map = await(qb('users')->pluck('name', 'email'));

    expect($map)
        ->toHaveKey('alice@test.com', 'Alice')
        ->toHaveKey('bob@test.com', 'Bob')
    ;
});

test('increment increases a column value', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'score' => 10],
    ]);

    $affected = await(qb('users')->where('name', 'Alice')->increment('score', 5));
    $user = await(qb('users')->where('name', 'Alice')->first());

    expect($affected)->toBe(1)
        ->and((float) $user->score)->toBe(15.0)
    ;
});

test('decrement decreases a column value and updates extra columns', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'score' => 10, 'status' => 'active'],
    ]);

    $affected = await(qb('users')->where('name', 'Alice')->decrement('score', 3, ['status' => 'inactive']));
    $user = await(qb('users')->where('name', 'Alice')->first());

    expect($affected)->toBe(1)
        ->and((float) $user->score)->toBe(7.0)
        ->and($user->status)->toBe('inactive')
    ;
});

test('increment and decrement handles zero affected rows if no match', function () {
    $affectedInc = await(qb('users')->where('name', 'Ghost')->increment('score', 5));
    $affectedDec = await(qb('users')->where('name', 'Ghost')->decrement('score', 5));

    expect($affectedInc)->toBe(0)
        ->and($affectedDec)->toBe(0)
    ;
});
