<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('chunk processes all rows across batches', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 10)
    ));

    $processed = 0;
    $batches   = 0;

    await(qb('users')->chunk(3, function (array $rows) use (&$processed, &$batches) {
        $processed += count($rows);
        $batches++;
    }));

    expect($processed)->toBe(10)
        ->and($batches)->toBe(4); 
});

test('chunk stops early when callback returns false', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 9)
    ));

    $processed = 0;

    await(qb('users')->chunk(3, function (array $rows) use (&$processed) {
        $processed += count($rows);
        return false;
    }));

    expect($processed)->toBe(3);
});

test('chunkById processes all rows without offset penalty', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 10)
    ));

    $processed = 0;

    await(qb('users')->chunkById(4, function (array $rows) use (&$processed) {
        $processed += count($rows);
    }));

    expect($processed)->toBe(10);
});

test('chunkById stops early when callback returns false', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 10)
    ));

    $processed = 0;

    await(qb('users')->chunkById(4, function (array $rows) use (&$processed) {
        $processed += count($rows);
        return false;
    }));

    expect($processed)->toBe(4);
});

test('each iterates over every row individually', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 5)
    ));

    $count = 0;

    await(qb('users')->each(function () use (&$count) {
        $count++;
    }));

    expect($count)->toBe(5);
});

test('each stops early when callback returns false', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 10)
    ));

    $count = 0;

    await(qb('users')->each(function () use (&$count) {
        $count++;
        return false;
    }));

    expect($count)->toBe(1);
});