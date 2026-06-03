<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('orWhereNotExists applies alternative negative subquery', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'banned'],
        ['name' => 'Charlie', 'email' => 'c@test.com', 'status' => 'banned'],
    ]);

    $charlie = await(qb('users')->where('name', 'Charlie')->first());
    TestSchema::insertOrders(client(), [['user_id' => $charlie->id, 'total' => 100]]);

    $result = await(qb('users')
        ->where('status', 'active')
        ->orWhereNotExists(fn ($q) => $q->from('orders')->whereRaw('orders.user_id = users.id'))
        ->orderBy('name')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[1]->name)->toBe('Bob')
    ;
});

test('whereNested and orWhereNested apply proper logical grouping', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 20, 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'age' => 16, 'status' => 'active'],
        ['name' => 'Charlie', 'email' => 'c@test.com', 'age' => 30, 'status' => 'banned'],
        ['name' => 'Dave', 'email' => 'd@test.com', 'age' => 40, 'status' => 'banned'],
    ]);

    $result = await(qb('users')
        ->whereNested(fn ($q) => $q->where('status', 'active')->where('age', '>', 18))
        ->orWhereNested(fn ($q) => $q->where('status', 'banned')->where('age', '>', 35))
        ->orderBy('name')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[1]->name)->toBe('Dave')
    ;
});

test('addSelect appends to existing select clauses', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]]);

    $result = await(qb('users')
        ->toArray()
        ->select('name')
        ->addSelect('email', 'age')
        ->first());

    $row = $result;

    expect($row)
        ->toHaveKey('name')
        ->toHaveKey('email')
        ->toHaveKey('age')
        ->not->toHaveKey('id')
    ;
});

test('orWhereColumn applies alternative column comparison', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'pending'],
        ['name' => 'Charlie', 'email' => 'Charlie'],
    ]);

    $result = await(qb('users')
        ->where('status', 'pending')
        ->orWhereColumn('name', 'email')
        ->orderBy('name')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Bob')
        ->and($result[1]->name)->toBe('Charlie')
    ;
});

test('orWhereRaw applies raw alternative conditions safely', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 20],
        ['name' => 'Bob', 'email' => 'b@test.com', 'age' => 30],
        ['name' => 'Charlie', 'email' => 'c@test.com', 'age' => 40],
    ]);

    $result = await(qb('users')
        ->where('name', 'Alice')
        ->orWhereRaw('age > ?', [35])
        ->orderBy('name')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[1]->name)->toBe('Charlie')
    ;
});

test('orHaving and orHavingRaw apply alternative aggregations', function () {
    TestSchema::insertOrders(client(), [
        ['user_id' => 1, 'total' => 10],
        ['user_id' => 2, 'total' => 100],
        ['user_id' => 3, 'total' => 10],
        ['user_id' => 3, 'total' => 10],
    ]);

    $result = await(qb('orders')
        ->selectRaw('user_id, SUM(total) as grand_total')
        ->groupBy('user_id')
        ->havingRaw('SUM(total) > ?', [50])
        ->orHavingRaw('COUNT(id) > ?', [1])
        ->orderBy('user_id')
        ->get());

    expect($result)->toHaveCount(2)
        ->and((int) $result[0]->user_id)->toBe(2)
        ->and((int) $result[1]->user_id)->toBe(3)
    ;
});

test('resetWhere clears all conditions and bindings', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com'],
    ]);

    $query = qb('users')->where('name', 'Alice')->where('age', '>', 18);

    $resetQuery = $query->resetWhere();

    $result = await($resetQuery->get());

    expect($result)->toHaveCount(2)
        ->and($resetQuery->getBindings())->toBeEmpty();
});
