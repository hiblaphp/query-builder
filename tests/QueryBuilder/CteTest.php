<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('executes a basic CTE select query successfully on the database', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'bob@test.com',   'status' => 'inactive'],
    ]);

    $result = await(qb('active_users')
        ->select('id', 'name')
        ->with('active_users', function ($q) {
            return $q->from('users')
                ->select('id', 'name')
                ->where('status', 'active')
            ;
        })
        ->orderBy('name')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes a CTE query joined to a standard table with parameter alignment', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'bob@test.com',   'status' => 'inactive'],
    ]);
    $alice = await(qb('users')->where('name', 'Alice')->first());
    $bob = await(qb('users')->where('name', 'Bob')->first());

    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
        ['user_id' => $bob->id, 'total' => 20],
    ]);

    $result = await(qb('active_users')
        ->select('active_users.name', 'orders.total')
        ->with('active_users', function ($q) {
            return $q->from('users')
                ->select('id', 'name')
                ->where('status', 'active')
            ;
        })
        ->innerJoin('orders', 'active_users.id = orders.user_id')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
        ->and((float) $result[0]->total)->toBe(100.0)
    ;
});

test('executes a recursive CTE to generate sequences natively in the database', function () {
    $result = await(newQb()
        ->select('n')
        ->withRecursive('seq', function ($q) {
            return $q->selectRaw('1 AS n')
                ->unionAll(function ($union) {
                    return $union->from('seq')
                        ->selectRaw('n + 1')
                        ->where('n', '<', 5)
                    ;
                })
            ;
        })
        ->from('seq')
        ->orderBy('n', 'ASC')
        ->get());

    expect($result)->toHaveCount(5)
        ->and((int) $result[0]->n)->toBe(1)
        ->and((int) $result[1]->n)->toBe(2)
        ->and((int) $result[2]->n)->toBe(3)
        ->and((int) $result[3]->n)->toBe(4)
        ->and((int) $result[4]->n)->toBe(5)
    ;
});

test('executes multiple dependent CTEs where the second queries from the first', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'bob@test.com',   'status' => 'inactive'],
    ]);

    $result = await(qb('alice_only')
        ->select('id', 'name')
        ->with('active_users', function ($q) {
            return $q->from('users')
                ->select('id', 'name')
                ->where('status', 'active')
            ;
        })
        ->with('alice_only', function ($q) {
            return $q->from('active_users')
                ->select('id', 'name')
                ->where('name', 'Alice')
            ;
        })
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes CTE with complex whereIn, ordering, and pagination limits', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob',   'email' => 'bob@test.com'],
        ['name' => 'Carol', 'email' => 'carol@test.com'],
    ]);

    $result = await(qb('paged_users')
        ->select('name')
        ->with('paged_users', function ($q) {
            return $q->from('users')
                ->whereIn('name', ['Alice', 'Bob', 'Carol'])
            ;
        })
        ->orderBy('name', 'ASC')
        ->limit(2, 1)
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Bob')
        ->and($result[1]->name)->toBe('Carol')
    ;
});

test('executes counts and aggregations on top of CTE structures successfully', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'alice@test.com']]);
    $alice = await(qb('users')->first());

    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
        ['user_id' => $alice->id, 'total' => 300],
    ]);

    $query = qb('alice_orders')
        ->with('alice_orders', function ($q) use ($alice) {
            return $q->from('orders')->where('user_id', $alice->id);
        })
    ;

    $sum = await($query->sum('total'));
    $avg = await($query->avg('total'));
    $count = await($query->count());

    expect((float) $sum)->toBe(400.0)
        ->and((float) $avg)->toBe(200.0)
        ->and($count)->toBe(2)
    ;
});

test('executes a self-join on the same CTE table concurrently', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'alice@test.com']]);
    $alice = await(qb('users')->first());

    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
        ['user_id' => $alice->id, 'total' => 300],
    ]);

    $result = await(qb('users as u')
        ->selectDistinct('u.name')
        ->with('user_orders', function ($q) {
            return $q->from('orders')->select('user_id', 'total');
        })
        ->innerJoin('user_orders as o1', 'u.id = o1.user_id AND o1.total = 100')
        ->innerJoin('user_orders as o2', 'u.id = o2.user_id AND o2.total = 300')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes pessimistic locking lockForUpdate on top of CTE queries within transactions', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active']]);

    /** @var stdClass */
    $result = await(newQb()->transaction(function ($tx) {
        return $tx->from('active_users')
            ->with('active_users', function ($q) {
                return $q->from('users')->select('id', 'name')->where('status', 'active');
            })
            ->lockForUpdate()
            ->first()
        ;
    }));

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Alice')
    ;
});

test('executes UNION ALL combining a CTE table with a standard database table', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'bob@test.com',   'status' => 'inactive'],
    ]);

    $result = await(qb('active_users')
        ->select('name')
        ->with('active_users', function ($q) {
            return $q->from('users')->select('name')->where('status', 'active');
        })
        ->unionAll(function ($union) {
            return $union->from('users')->select('name')->where('status', 'inactive');
        })
        ->orderBy('name', 'ASC')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[1]->name)->toBe('Bob')
    ;
});

test('executes whereExists subquery referencing an outer-scoped CTE table', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'bob@test.com',   'status' => 'inactive'],
    ]);
    $alice = await(qb('users')->where('name', 'Alice')->first());
    $bob = await(qb('users')->where('name', 'Bob')->first());

    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 500],
        ['user_id' => $bob->id, 'total' => 20],
    ]);

    $result = await(qb('orders')
        ->select('orders.total')
        ->with('active_users', function ($q) {
            return $q->from('users')->where('status', 'active');
        })
        ->whereExists(function ($sub) {
            return $sub->from('active_users')
                ->whereColumn('active_users.id', 'orders.user_id')
            ;
        })
        ->get());

    expect($result)->toHaveCount(1)
        ->and((float) $result[0]->total)->toBe(500.0)
    ;
});
