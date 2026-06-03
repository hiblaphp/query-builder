<?php

declare(strict_types=1);

use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\RowStream;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\async;
use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('stream returns a RowStream instance', function () {
    $stream = await(qb('users')->stream());

    expect($stream)->toBeInstanceOf(RowStream::class);
});

test('stream yields all rows when iterated', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 5)
    ));

    $stream = await(qb('users')->stream());
    $count = 0;

    foreach ($stream as $row) {
        $count++;
    }

    expect($count)->toBe(5);
});

test('stream yields objects by default', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
    ]);

    $stream = await(qb('users')->stream());

    foreach ($stream as $row) {
        expect($row)->toBeObject();
    }
});

test('stream yields arrays when toArray mode is set', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
    ]);

    $stream = await(qb('users')->toArray()->stream());

    foreach ($stream as $row) {
        expect($row)->toBeArray()->toHaveKey('name');
    }
});

test('chunkStream groups rows into correct batch sizes', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 7)
    ));

    $batches = [];

    await(qb('users')->chunkStream(3, function (array $batch) use (&$batches) {
        $batches[] = count($batch);
    }));

    expect($batches)->toBe([3, 3, 1]);
});

test('rawStream yields rows for a raw sql query', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'a@test.com'],
        ['name' => 'Bob',   'email' => 'b@test.com'],
    ]);

    $stream = await(
        (new QueryBuilder(client(), ClientFactory::driverEnum()))
            ->rawStream('SELECT id, name FROM users')
    );

    $rows = [];
    foreach ($stream as $row) {
        $rows[] = $row;
    }

    expect($rows)->toHaveCount(2);
});

test('stream maintains low memory profile when processing large result sets', function () {
    $totalRows = 20000;

    $batches = array_chunk(range(1, $totalRows), 1000);

    foreach ($batches as $index => $batch) {
        $insertData = array_map(
            fn ($i) => ['name' => "User $i", 'email' => "user$i-1@test.com", 'score' => 99.99],
            $batch
        );

        await(qb('users')->insertBatch($insertData));
    }

    gc_collect_cycles();
    $startMemory = memory_get_usage();

    $stream = await(qb('users')->stream(bufferSize: 100));
    $count = 0;

    await(async(function () use ($stream, &$count) {
        foreach ($stream as $row) {
            $count++;
        }
    }));

    gc_collect_cycles();
    $endMemory = memory_get_usage();
    $memoryUsedMb = ($endMemory - $startMemory) / 1024 / 1024;

    expect($count)->toBe($totalRows)
        ->and($memoryUsedMb)->toBeLessThan(2.0)
    ;
});

test('chunkStream maintains low memory profile', function () {
    $totalRows = 20000;

    $batches = array_chunk(range(1, $totalRows), 1000);

    foreach ($batches as $index => $batch) {
        $insertData = array_map(
            fn ($i) => ['name' => "User $i", 'email' => "user$i-2@test.com", 'score' => 99.99],
            $batch
        );
        await(qb('users')->insertBatch($insertData));
    }

    gc_collect_cycles();
    $startMemory = memory_get_usage();

    $count = 0;

    await(qb('users')->chunkStream(500, function (array $batch) use (&$count) {
        $count += count($batch);
    }));

    gc_collect_cycles();
    $endMemory = memory_get_usage();
    $memoryUsedMb = ($endMemory - $startMemory) / 1024 / 1024;

    expect($count)->toBe($totalRows)
        ->and($memoryUsedMb)->toBeLessThan(2.0)
    ;
});
