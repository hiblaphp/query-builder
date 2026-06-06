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
        ->and((float) $result[0]->total)->toBe(150.0)
    ;
});

test('leftJoin retrieves all left records even if right is missing', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com'],
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
        ->and($result[1]->total)->toBeNull()
    ;
});

test('rightJoin retrieves all right records even if left is missing', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->where('name', 'Alice')->first());

    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
        ['user_id' => 999, 'total' => 50],
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->rightJoin('orders', 'users.id = orders.user_id')
        ->orderBy('orders.total', 'DESC')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[1]->name)->toBeNull()
    ;
});

test('crossJoin produces a cartesian product of two tables', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com'],
    ]);
    TestSchema::insertOrders(client(), [
        ['user_id' => 1, 'total' => 100],
        ['user_id' => 1, 'total' => 200],
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
        ->and($result[0]->name)->toBe('Alice')
    ;
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
        ->and($result[0]->same_buyer)->toBe('Alice')
    ;
});

test('joins work alongside where conditions on joined tables', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 150, 'status' => 'pending'],
        ['user_id' => $alice->id, 'total' => 300, 'status' => 'completed'],
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->innerJoin('orders', 'users.id = orders.user_id')
        ->where('orders.status', 'completed')
        ->get());

    expect($result)->toHaveCount(1)
        ->and((float) $result[0]->total)->toBe(300.0)
    ;
});

test('joins work with grouping and aggregation', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 10],
        ['user_id' => $alice->id, 'total' => 20],
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
        ['name' => 'Bob', 'email' => 'b@test.com'],
    ]);

    $alice = await(qb('users')->where('name', 'Alice')->first());
    TestSchema::insertOrders(client(), [['user_id' => $alice->id, 'total' => 10]]);

    $orphans = await(qb('users')
        ->select('users.name')
        ->leftJoin('orders', 'users.id = orders.user_id')
        ->whereNull('orders.id')
        ->get());

    expect($orphans)->toHaveCount(1)
        ->and($orphans[0]->name)->toBe('Bob')
    ;
});

test('join condition can handle complex ON clauses', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 10, 'status' => 'pending'],
        ['user_id' => $alice->id, 'total' => 50, 'status' => 'completed'],
    ]);

    $result = await(qb('users')
        ->select('orders.total')
        ->innerJoin('orders', 'users.id = orders.user_id AND orders.status = \'completed\'')
        ->get());

    expect($result)->toHaveCount(1)
        ->and((float) $result[0]->total)->toBe(50.0)
    ;
});

test('advanced innerJoin using closure with parameter bindings', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 150, 'status' => 'completed'],
        ['user_id' => $alice->id, 'total' => 50, 'status' => 'pending'],
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->innerJoin('orders', function ($join) {
            return $join->on('users.id', '=', 'orders.user_id')
                ->where('orders.status', 'completed')
            ;
        })
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
        ->and((float) $result[0]->total)->toBe(150.0)
    ;
});

test('advanced leftJoin with nested group conditions', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob', 'email' => 'b@test.com'],
    ]);
    $alice = await(qb('users')->where('name', 'Alice')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100, 'status' => 'pending'],
        ['user_id' => $alice->id, 'total' => 200, 'status' => 'completed'],
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total', 'orders.status')
        ->leftJoin('orders', function ($join) {
            return $join->on('users.id', 'orders.user_id')
                ->whereGroup(function ($q) {
                    return $q->where('orders.status', 'pending')
                        ->orWhere('orders.status', 'completed')
                    ;
                })
            ;
        })
        ->orderBy('users.name', 'ASC')
        ->orderBy('orders.total', 'ASC')
        ->get());

    expect($result)->toHaveCount(3)
        ->and($result[0]->name)->toBe('Alice')
        ->and((float) $result[0]->total)->toBe(100.0)
        ->and($result[1]->name)->toBe('Alice')
        ->and((float) $result[1]->total)->toBe(200.0)
        ->and($result[2]->name)->toBe('Bob')
        ->and($result[2]->total)->toBeNull()
    ;
});

test('advanced join parameter bindings align correctly with main query where bindings', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25],
        ['name' => 'Bob', 'email' => 'b@test.com', 'age' => 15],
    ]);
    $alice = await(qb('users')->where('name', 'Alice')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 300, 'status' => 'completed'],
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->innerJoin('orders', function ($join) {
            return $join->on('users.id', 'orders.user_id')
                ->where('orders.status', 'completed')
            ;
        })
        ->where('users.age', '>', 18)
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
        ->and((float) $result[0]->total)->toBe(300.0)
    ;
});

test('advanced join with whereIn and whereNotIn on live database', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100, 'status' => 'completed'],
        ['user_id' => $alice->id, 'total' => 200, 'status' => 'pending'],
        ['user_id' => $alice->id, 'total' => 300, 'status' => 'failed'],
    ]);

    $result = await(qb('users')
        ->select('users.name', 'orders.total')
        ->innerJoin('orders', function ($join) {
            return $join->on('users.id', '=', 'orders.user_id')
                ->whereIn('orders.status', ['completed', 'pending'])
                ->whereNotIn('orders.status', ['failed', 'cancelled'])
            ;
        })
        ->orderBy('orders.total', 'ASC')
        ->get());

    expect($result)->toHaveCount(2)
        ->and((float) $result[0]->total)->toBe(100.0)
        ->and((float) $result[1]->total)->toBe(200.0)
    ;
});

test('advanced join containing subquery whereExists inside closure', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'inactive'],
    ]);
    $alice = await(qb('users')->where('name', 'Alice')->first());
    $bob = await(qb('users')->where('name', 'Bob')->first());

    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
        ['user_id' => $bob->id, 'total' => 200],
    ]);

    $result = await(qb('orders')
        ->select('users.name', 'orders.total')
        ->innerJoin('users', function ($join) {
            return $join->on('orders.user_id', '=', 'users.id')
                ->whereExists(function ($sub) {
                    return $sub->from('users as u')
                        ->whereColumn('u.id', 'users.id')
                        ->where('u.status', 'active')
                    ;
                })
            ;
        })
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
        ->and((float) $result[0]->total)->toBe(100.0)
    ;
});

test('advanced join with whereNull and whereNotNull conditions', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);
    $alice = await(qb('users')->first());
    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
    ]);

    await(qb('users')->where('id', $alice->id)->update(['status' => 'active', 'deleted_at' => null]));

    $result = await(qb('orders')
        ->select('users.name', 'orders.total')
        ->innerJoin('users', function ($join) {
            return $join->on('orders.user_id', '=', 'users.id')
                ->whereNotNull('users.status')
                ->whereNull('users.deleted_at')
            ;
        })
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});
