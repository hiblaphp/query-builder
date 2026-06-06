<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('when conditionally applies query modifications asynchronously', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'bob@test.com', 'status' => 'inactive'],
    ]);

    $searchActive = true;

    $results = await(qb('users')
        ->when($searchActive, fn ($q) => $q->where('status', 'active'))
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Alice')
    ;
});

test('unless conditionally applies query modifications asynchronously', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'bob@test.com', 'status' => 'inactive'],
    ]);

    $skipInactive = false;

    $results = await(qb('users')
        ->unless($skipInactive, fn ($q) => $q->where('status', 'active'))
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Alice')
    ;
});

test('latest sorts records by created_at desc by default', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'First', 'email' => 'first@test.com'],
        ['name' => 'Second', 'email' => 'second@test.com'],
    ]);

    await(qb('users')->where('name', 'First')->update([
        'created_at' => date('Y-m-d H:i:s', time() - 5),
    ]));

    $user = await(qb('users')->latest()->first());

    expect($user->name)->toBe('Second');
});

test('oldest sorts records by created_at asc by default', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'First', 'email' => 'first@test.com'],
        ['name' => 'Second', 'email' => 'second@test.com'],
    ]);

    await(qb('users')->where('name', 'First')->update([
        'created_at' => date('Y-m-d H:i:s', time() - 5),
    ]));

    $user = await(qb('users')->oldest()->first());

    expect($user->name)->toBe('First');
});

test('latest handles custom column sorting', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'First', 'email' => 'first@test.com', 'score' => 10],
        ['name' => 'Second', 'email' => 'second@test.com', 'score' => 50],
        ['name' => 'Third', 'email' => 'third@test.com', 'score' => 20],
    ]);

    $user = await(qb('users')->latest('score')->first());

    expect($user->name)->toBe('Second');
});

test('complex long when chain applies all active filters correctly', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice Smith', 'email' => 'alice@test.com', 'status' => 'active', 'score' => 90],
        ['name' => 'Bob Jones', 'email' => 'bob@test.com', 'status' => 'active', 'score' => 40],
        ['name' => 'Charlie Smith', 'email' => 'charlie@test.com', 'status' => 'inactive', 'score' => 95],
        ['name' => 'Dave Miller', 'email' => 'dave@test.com', 'status' => 'active', 'score' => 85],
    ]);

    $search = 'Smith';
    $status = 'active';
    $minScore = 80;
    $limit = 5;
    $ignoredFilter = null;

    $results = await(qb('users')
        ->when($search, fn ($q, $val) => $q->like('name', $val))
        ->when($status, fn ($q, $val) => $q->where('status', $val))
        ->when($ignoredFilter, fn ($q, $val) => $q->where('ignored_col', $val))
        ->when($minScore, fn ($q, $val) => $q->where('score', '>=', $val))
        ->when($limit, fn ($q, $val) => $q->limit($val))
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Alice Smith')
    ;
});

test('mixing when and unless applies correct criteria based on boolean states', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active', 'score' => 10],
        ['name' => 'Bob', 'email' => 'bob@test.com', 'status' => 'inactive', 'score' => 10],
        ['name' => 'Charlie', 'email' => 'charlie@test.com', 'status' => 'active', 'score' => 100],
    ]);

    $filterActive = true;
    $includeSuperusers = false;

    $results = await(qb('users')
        ->when($filterActive, fn ($q) => $q->where('status', 'active'))
        ->unless($includeSuperusers, fn ($q) => $q->where('score', '<=', 50))
        ->orderBy('name')
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Alice')
    ;
});

test('evaluates closure as condition on live database execution', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob', 'email' => 'bob@test.com'],
    ]);

    $results = await(qb('users')
        ->when(
            fn ($q) => true,
            fn ($q) => $q->where('name', 'Alice')
        )
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Alice')
    ;
});

test('evaluates invokable class as condition on live database execution', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob', 'email' => 'bob@test.com'],
    ]);

    $invokable = new class () {
        public function __invoke($q): bool
        {
            return true;
        }
    };

    $results = await(qb('users')
        ->when($invokable, fn ($q) => $q->where('name', 'Bob'))
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Bob')
    ;
});

test('safely treats string callables as raw data on live database execution', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'count', 'email' => 'count@test.com'],
        ['name' => 'Bob', 'email' => 'bob@test.com'],
    ]);

    $search = 'count';

    $results = await(qb('users')
        ->when($search, fn ($q, $val) => $q->where('name', $val))
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('count')
    ;
});

test('treats non-invokable objects as truthy values on live database execution', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 25],
        ['name' => 'Bob', 'email' => 'bob@test.com', 'age' => 30],
    ]);

    $dto = new stdClass();
    $dto->targetAge = 25;

    $results = await(qb('users')
        ->when($dto, fn ($q, $val) => $q->where('age', $val->targetAge))
        ->get());

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Alice');
});
