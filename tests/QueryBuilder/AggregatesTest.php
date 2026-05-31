<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('count returns the total number of rows', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob',   'email' => 'b@test.com'],
    ]);

    expect(await(qb('users')->count()))->toBe(2);
});

test('count with where returns only matching rows', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob',   'email' => 'b@test.com', 'status' => 'inactive'],
    ]);

    expect(await(qb('users')->where('status', 'active')->count()))->toBe(1);
});

test('sum returns the correct total', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'score' => 10.50],
        ['name' => 'Bob',   'email' => 'b@test.com', 'score' => 20.50],
    ]);

    expect((float) await(qb('users')->sum('score')))->toBe(31.0);
});

test('avg returns the correct average', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'score' => 10],
        ['name' => 'Bob',   'email' => 'b@test.com', 'score' => 20],
    ]);

    expect((float) await(qb('users')->avg('score')))->toBe(15.0);
});

test('min returns the smallest value in the column', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 20],
        ['name' => 'Bob',   'email' => 'b@test.com', 'age' => 40],
    ]);

    expect((int) await(qb('users')->min('age')))->toBe(20);
});

test('max returns the largest value in the column', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'age' => 20],
        ['name' => 'Bob',   'email' => 'b@test.com', 'age' => 40],
    ]);

    expect((int) await(qb('users')->max('age')))->toBe(40);
});

test('exists returns false when table is empty', function () {
    expect(await(qb('users')->exists()))->toBeFalse();
});

test('exists returns true when at least one row matches', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
    ]);

    expect(await(qb('users')->exists()))->toBeTrue();
});

test('exists with where only checks matching rows', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
    ]);

    expect(await(qb('users')->where('status', 'inactive')->exists()))->toBeFalse();
    expect(await(qb('users')->where('status', 'active')->exists()))->toBeTrue();
});