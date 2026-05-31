<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('where with explicit operator filters correctly', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 30],
        ['name' => 'Bob',   'email' => 'b@test.com', 'age' => 17],
    ]);

    $result = await(qb('users')->where('age', '>=', 18)->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('orWhere returns rows matching either condition', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'b@test.com', 'status' => 'banned'],
        ['name' => 'Carol', 'email' => 'c@test.com', 'status' => 'pending'],
    ]);

    $result = await(qb('users')
        ->where('status', 'active')
        ->orWhere('status', 'banned')
        ->get());

    expect($result)->toHaveCount(2);
});

test('whereIn filters rows whose column value is in the given list', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'b@test.com', 'status' => 'inactive'],
        ['name' => 'Carol', 'email' => 'c@test.com', 'status' => 'banned'],
    ]);

    $result = await(qb('users')->whereIn('status', ['active', 'banned'])->get());

    expect($result)->toHaveCount(2);
});

test('whereNotIn excludes rows whose column value is in the given list', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'b@test.com', 'status' => 'inactive'],
    ]);

    $result = await(qb('users')->whereNotIn('status', ['inactive'])->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('whereNull and whereNotNull filter on null column', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 30],
        ['name' => 'Bob',   'email' => 'b@test.com', 'age' => null],
    ]);

    $withAge = await(qb('users')->whereNotNull('age')->get());
    $withoutAge = await(qb('users')->whereNull('age')->get());

    expect($withAge)->toHaveCount(1)
        ->and($withoutAge)->toHaveCount(1)
    ;
});

test('whereBetween returns only rows within the inclusive range', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 20],
        ['name' => 'Bob',   'email' => 'b@test.com', 'age' => 30],
        ['name' => 'Carol', 'email' => 'c@test.com', 'age' => 40],
    ]);

    $result = await(qb('users')->whereBetween('age', [25, 35])->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Bob')
    ;
});

test('whereNested groups conditions with correct precedence', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active',   'age' => 30],
        ['name' => 'Bob',   'email' => 'b@test.com', 'status' => 'inactive', 'age' => 30],
        ['name' => 'Carol', 'email' => 'c@test.com', 'status' => 'active',   'age' => 17],
    ]);

    $result = await(qb('users')
        ->where('status', 'active')
        ->whereNested(fn ($q) => $q->where('age', '>=', 18))
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('like performs a case-insensitive partial match', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice Smith', 'email' => 'a@test.com'],
        ['name' => 'Bob Jones',   'email' => 'b@test.com'],
    ]);

    $result = await(qb('users')->like('name', 'Alice')->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice Smith')
    ;
});

test('whereExists filters rows that have a related record', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob',   'email' => 'b@test.com'],
    ]);

    $alice = await(qb('users')->where('email', 'a@test.com')->first());

    TestSchema::insertOrders(client(), [
        ['user_id' => $alice->id, 'total' => 100],
    ]);

    $result = await(qb('users')
        ->whereExists(fn ($q) => $q
            ->from('orders')
            ->whereRaw('orders.user_id = users.id'))
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('whereRaw accepts a raw condition with bindings', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 30],
        ['name' => 'Bob',   'email' => 'b@test.com', 'age' => 17],
    ]);

    $result = await(qb('users')->whereRaw('age > ?', [18])->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('select limits the columns returned', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
    ]);

    $result = await(qb('users')->select('name')->get());

    expect($result[0])->toBeObject()
        ->and((array) $result[0])->toHaveKey('name')
        ->and((array) $result[0])->not->toHaveKey('email')
    ;
});

test('orderBy sorts results correctly', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Charlie', 'email' => 'c@test.com'],
        ['name' => 'Alice',   'email' => 'a@test.com'],
        ['name' => 'Bob',     'email' => 'b@test.com'],
    ]);

    $asc = await(qb('users')->orderBy('name', 'ASC')->pluck('name'));
    $desc = await(qb('users')->orderBy('name', 'DESC')->pluck('name'));

    expect($asc)->toBe(['Alice', 'Bob', 'Charlie'])
        ->and($desc)->toBe(['Charlie', 'Bob', 'Alice'])
    ;
});

test('limit and offset paginate raw results', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 5)
    ));

    $result = await(qb('users')->orderBy('id')->limit(2, 2)->get());

    expect($result)->toHaveCount(2);
});
