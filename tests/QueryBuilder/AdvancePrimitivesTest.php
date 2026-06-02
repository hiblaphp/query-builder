<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;
use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('groupBy and having filter aggregated results', function () {
    TestSchema::insertOrders(client(), [
        ['user_id' => 1, 'total' => 50],
        ['user_id' => 1, 'total' => 100],
        ['user_id' => 2, 'total' => 20],
    ]);

    $result = await(qb('orders')
        ->selectRaw('user_id, SUM(total) as grand_total')
        ->groupBy('user_id')
        ->havingRaw('SUM(total) > ?', [100]) 
        ->get());

    expect($result)->toHaveCount(1)
        ->and((int) $result[0]->user_id)->toBe(1)
        ->and((float) $result[0]->grand_total)->toBe(150.0);
});

test('whereColumn compares two columns in the same table', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'Alice'], 
        ['name' => 'Bob', 'email' => 'bob@test.com'] 
    ]);

    $result = await(qb('users')->whereColumn('name', 'email')->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice');
});

test('whereSub filters based on a subquery', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com'],
    ]);

    $alice = await(qb('users')->where('name', 'Alice')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 100]]);

    $result = await(qb('users')
        ->whereSub('id', 'IN', fn ($q) => $q->from('orders')->select('user_id'))
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice');
});

test('union combines multiple query results without duplicates', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'active'],
    ]);

    $result = await(qb('users')
        ->select('status')
        ->union(fn ($q) => $q->from('users')->select('status'))
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->status)->toBe('active');
});

test('unionAll combines multiple query results keeping duplicates', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'active'],
    ]);

    $result = await(qb('users')
        ->select('status')
        ->unionAll(fn ($q) => $q->from('users')->select('status'))
        ->get());

    expect($result)->toHaveCount(4);
});

test('whereGroup wraps conditions in parentheses', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'banned'],
        ['name' => 'Charlie', 'email' => 'c@test.com', 'status' => 'pending'],
    ]);

    $result = await(qb('users')
        ->where('name', 'Alice')
        ->whereGroup(function($q) {
            return $q->where('status', 'banned')->where('email', 'b@test.com');
        }, 'OR')
        ->get());

    expect($result)->toHaveCount(2);
});

test('whereNotExists filters out records that match subquery', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'], 
        ['name' => 'Bob', 'email' => 'b@test.com'],  
    ]);

    $alice = await(qb('users')->where('name', 'Alice')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 100]]);

    $result = await(qb('users')
        ->whereNotExists(fn ($q) => $q->from('orders')->whereRaw('orders.user_id = users.id'))
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Bob');
});

test('orWhereExists acts as an alternative subquery match', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'], 
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'banned'],  
        ['name' => 'Charlie', 'email' => 'c@test.com', 'status' => 'banned'], 
    ]);

    $bob = await(qb('users')->where('name', 'Bob')->first());
    TestSchema::insertOrders(client(), [['user_id' => $bob->id, 'total' => 100]]);

    $result = await(qb('users')
        ->where('status', 'active')
        ->orWhereExists(fn ($q) => $q->from('orders')->whereRaw('orders.user_id = users.id'))
        ->orderBy('name')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[1]->name)->toBe('Bob');
});

test('havingRaw safely accepts bindings', function () {
    TestSchema::insertOrders(client(), [
        ['user_id' => 1, 'total' => 50],
        ['user_id' => 1, 'total' => 100],
    ]);

    $result = await(qb('orders')
        ->selectRaw('user_id, SUM(total) as grand_total')
        ->groupBy('user_id')
        ->havingRaw('SUM(total) > ?', [100])
        ->get());

    expect($result)->toHaveCount(1);
});

test('selectDistinct retrieves unique combinations of columns', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'active'],
        ['name' => 'Charlie', 'email' => 'c@test.com', 'status' => 'banned'],
    ]);

    $result = await(qb('users')->selectDistinct('status')->orderBy('status')->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->status)->toBe('active')
        ->and($result[1]->status)->toBe('banned');
});