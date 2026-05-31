<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;
use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('innerJoin retrieves matching records across tables', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 150]]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->innerJoin('orders', 'users.id = orders.user_id')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
        ->and((float) $result[0]->total)->toBe(150.0);
});

test('leftJoin retrieves all left records even if right is missing', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com']
    ]);
    $alice = await(qb('users')->where('name', 'Alice')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 100]]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->leftJoin('orders', 'users.id = orders.user_id')
        ->orderBy('users.name')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[0]->total)->not->toBeNull()
        ->and($result[1]->name)->toBe('Bob')
        ->and($result[1]->total)->toBeNull();
});

test('rightJoin retrieves all right records even if left is missing', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->where('name', 'Alice')->first());
    
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
        ['user_id' => 999, 'total' => 50] 
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->rightJoin('orders', 'users.id = orders.user_id')
        ->orderBy('orders.total', 'DESC')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice') 
        ->and($result[1]->name)->toBeNull();   
});

test('crossJoin produces a cartesian product of two tables', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com']
    ]);
    TestSchema::insertOrders(client(), [
        ['user_id' => 1, 'total' => 100],
        ['user_id' => 1, 'total' => 200]
    ]);

    $result = await(qb('users')->crossJoin('orders')->get());

    expect($result)->toHaveCount(4);
});

test('joins support table aliases', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 150]]);

    $result = await(qb('users as u')
        ->select('u.name', 'o.total')
        ->innerJoin('orders as o', 'u.id = o.user_id')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice');
});

test('joins can chain multiple tables', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 150]]);

    $result = await(qb('users as u1')
        ->select('u1.name as buyer', 'u2.name as same_buyer')
        ->innerJoin('orders as o', 'u1.id = o.user_id')
        ->innerJoin('users as u2', 'o.user_id = u2.id')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->buyer)->toBe('Alice')
        ->and($result[0]->same_buyer)->toBe('Alice');
});

test('joins work alongside where conditions on joined tables', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 150, 'status' => 'pending'],
        ['user_id' => $alice->id, 'total' => 300, 'status' => 'completed']
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->innerJoin('orders', 'users.id = orders.user_id')
        ->where('orders.status', 'completed')
        ->get());

    expect($result)->toHaveCount(1)
        ->and((float) $result[0]->total)->toBe(300.0);
});

test('joins work with grouping and aggregation', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 10],
        ['user_id' => $alice->id, 'total' => 20]
    ]);

    $result = await(qb('users')
        ->selectRaw('users.name, SUM(orders.total) as final_total')
        ->innerJoin('orders', 'users.id = orders.user_id')
        ->groupBy('users.name')
        ->get());

    expect((float) $result[0]->final_total)->toBe(30.0);
});

test('leftJoin combined with whereNull finds orphan records', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'], 
        ['name' => 'Bob', 'email' => 'b@test.com']   
    ]);
    
    $alice = await(qb('users')->where('name', 'Alice')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 10]]);

    $orphans = await(qb('users')
        ->select('users.name')
        ->leftJoin('orders', 'users.id = orders.user_id')
        ->whereNull('orders.id')
        ->get());

    expect($orphans)->toHaveCount(1)
        ->and($orphans[0]->name)->toBe('Bob');
});

test('join condition can handle complex ON clauses', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 10, 'status' => 'pending'],
        ['user_id' => $alice->id, 'total' => 50, 'status' => 'completed']
    ]);

    $result = await(qb('users')
        ->select('orders.total')
        ->innerJoin('orders', 'users.id = orders.user_id AND orders.status = \'completed\'')
        ->get());

    expect($result)->toHaveCount(1)
        ->and((float) $result[0]->total)->toBe(50.0);
});