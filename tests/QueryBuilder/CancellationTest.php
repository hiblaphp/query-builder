<?php

declare(strict_types=1);

use Hibla\EventLoop\Loop;
use Hibla\Mysql\MysqlClient;
use Hibla\Postgres\PostgresClient;
use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\QueryBuilder;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\await;
use function Hibla\delay;

function createCancellationClient(bool $enableServerSideCancellation): array
{
    $config = ClientFactory::config();
    $config['enable_server_side_cancellation'] = $enableServerSideCancellation;
    $config['max_connections'] = 1;
    $config['min_connections'] = 1;

    $driver = ClientFactory::driverEnum();

    $client = $driver === DatabaseDriver::Postgres
        ? new PostgresClient($config)
        : new MysqlClient($config);

    $qb = new QueryBuilder($client, $driver);

    return [$client, $qb, $driver];
}

function getSleepQuery(DatabaseDriver $driver, int $seconds): string
{
    return $driver === DatabaseDriver::Postgres
        ? "SELECT pg_sleep({$seconds}) AS sleep_result"
        : "SELECT SLEEP({$seconds}) AS sleep_result";
}

function getSlowQuery(int $seconds): string
{
    return ClientFactory::driverEnum() === DatabaseDriver::Postgres
        ? "SELECT pg_sleep({$seconds})"
        : "SELECT SLEEP({$seconds})";
}

beforeEach(function () {
    TestSchema::truncateAll(ClientFactory::make());
});

test('query promise cancellation propagates to the server and cleanly recovers the connection', function () {
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $sleepQuery = getSleepQuery($driver, 5);
        $startTime = hrtime(true);

        $promise = $qb->raw($sleepQuery);

        Loop::addTimer(0.1, function () use ($promise) {
            $promise->cancel();
        });

        try {
            await($promise);
            test()->fail('Promise should have been cancelled.');
        } catch (CancelledException $e) {
            expect($e->getMessage())->toContain('cancel');
        }

        $durationMs = (hrtime(true) - $startTime) / 1e6;

        expect($durationMs)->toBeLessThan(2000);

        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
    } finally {
        await($client->closeAsync());
    }
});

test('transaction promise cancellation aborts the running query and rolls back safely', function () {
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $sleepQuery = getSleepQuery($driver, 5);

        $promise = $qb->transaction(function ($trx) use ($sleepQuery) {
            await($trx->from('users')->insert(['name' => 'CancelMe', 'email' => 'cancel@test.com']));
            await($trx->raw($sleepQuery));
            await($trx->from('users')->insert(['name' => 'NeverReaches', 'email' => 'never@test.com']));
        });

        Loop::addTimer(0.1, function () use ($promise) {
            $promise->cancel();
        });

        try {
            await($promise);
            test()->fail('Transaction promise should have been cancelled.');
        } catch (CancelledException $e) {
            expect(true)->toBeTrue();
        }

        await(delay(0.05));

        $count = await($qb->from('users')->count());
        expect($count)->toBe(0);

        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
    } finally {
        await($client->closeAsync());
    }
});

test('stream cancellation propagates to the server and stops delivery', function () {
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $rows = array_map(fn($i) => ['name' => "User $i", 'email' => "stream$i@test.com"], range(1, 100));
        await($qb->from('users')->insertBatch($rows));

        $count = 0;

        $promise = $qb->from('users')->each(function ($row) use (&$count) {
            $count++;
            await(delay(0.05));
        });

        Loop::addTimer(0.3, function () use ($promise) {
            $promise->cancel();
        });

        try {
            await($promise);
            test()->fail('Stream promise should have been cancelled.');
        } catch (CancelledException $e) {
            expect(true)->toBeTrue();
        }

        expect($count)->toBeGreaterThan(0)->toBeLessThan(50);

        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
    } finally {
        await($client->closeAsync());
    }
});

test('cancellation without server-side support drops the connection safely', function () {
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: false);

    try {
        $sleepQuery = getSleepQuery($driver, 2);
        $promise = $qb->raw($sleepQuery);

        $startTime = hrtime(true);

        Loop::addTimer(0.1, function () use ($promise) {
            $promise->cancel();
        });

        try {
            await($promise);
            test()->fail('Promise should have been cancelled.');
        } catch (CancelledException $e) {
            expect(true)->toBeTrue();
        }

        $durationMs = (hrtime(true) - $startTime) / 1e6;

        // Postgres pg_close() blocks synchronously if a query is active.
        // MySQL driver handles it non-blockingly. We adjust assertions accordingly.
        if ($driver !== DatabaseDriver::Postgres) {
            expect($durationMs)->toBeLessThan(1500);
        }

        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside each() gracefully cancels the stream without throwing an exception', function () {
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $rows = array_map(fn($i) => ['name' => "User $i", 'email' => "internal$i@test.com"], range(1, 100));
        await($qb->from('users')->insertBatch($rows));

        $count = 0;

        $promise = $qb->from('users')->each(function ($row) use (&$count) {
            $count++;
            if ($count === 5) {
                return false;
            }
        });

        await($promise);

        expect($count)->toBe(5);

        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside chunkStream() gracefully cancels the stream without throwing', function () {
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $rows = array_map(fn($i) => ['name' => "User $i", 'email' => "chunk$i@test.com"], range(1, 100));
        await($qb->from('users')->insertBatch($rows));

        $processedRows = 0;
        $chunkCount = 0;

        $promise = $qb->from('users')->chunkStream(10, function (array $chunk) use (&$processedRows, &$chunkCount) {
            $chunkCount++;
            $processedRows += count($chunk);

            if ($chunkCount === 2) {
                return false;
            }
        });

        await($promise);

        expect($chunkCount)->toBe(2)
            ->and($processedRows)->toBe(20);

        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside chunk() gracefully halts pagination without throwing', function () {
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $rows = array_map(fn($i) => ['name' => "User $i", 'email' => "pagechunk$i@test.com"], range(1, 50));
        await($qb->from('users')->insertBatch($rows));

        $processedRows = 0;
        $chunkCount = 0;

        $promise = $qb->from('users')->orderBy('id')->chunk(10, function (array $chunk) use (&$processedRows, &$chunkCount) {
            $chunkCount++;
            $processedRows += count($chunk);

            if ($chunkCount === 3) {
                return false;
            }
        });

        await($promise);

        expect($chunkCount)->toBe(3)
            ->and($processedRows)->toBe(30);

        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside chunkById() gracefully halts pagination without throwing', function () {
    TestSchema::insertUsers(client(), array_map(
        fn($i) => ['name' => "User $i", 'email' => "user$i@test.com", 'score' => 10],
        range(1, 10)
    ));

    $count = 0;
    $promise = qb('users')->chunkById(3, function (array $batch) use (&$count) {
        $count += count($batch);
        if ($count >= 6) {
            return false;
        }
    });

    await($promise);

    expect($count)->toBe(6)
        ->and($promise->isCancelled())->toBeFalse();
});

test('calling cancel multiple times is idempotent and does not break the pool', function () {
    $sql = getSlowQuery(2);
    $promise = qb('users')->raw($sql);

    $promise->cancel();
    $promise->cancel();
    $promise->cancel();

    try {
        await($promise);
    } catch (CancelledException) {
        // Expected
    }

    $recovery = await(qb('users')->raw('SELECT 1 AS ok'));
    expect((int)$recovery[0]->ok)->toBe(1);
});

test('concurrent mass cancellation safely kills all queries and recovers the pool', function () {
    $sql = getSlowQuery(3);

    $promises = [
        qb('users')->raw($sql),
        qb('users')->raw($sql),
        qb('users')->raw($sql),
    ];

    await(delay(0.1));

    foreach ($promises as $promise) {
        $promise->cancel();
    }
    await(delay(0.2));

    $recoveries = await(Promise::all([
        qb('users')->raw('SELECT 1'),
        qb('users')->raw('SELECT 1'),
        qb('users')->raw('SELECT 1'),
    ]));

    expect($recoveries)->toHaveCount(3);
});

test('commit and rollback promises are uninterruptible', function () {
    $tx = await(newQb()->beginTransaction());

    await($tx->from('users')->insert(['name' => 'Cancel Test', 'email' => 'cancel@test.com']));

    $commitPromise = $tx->commit();

    $commitPromise->cancel();

    await(delay(0.1));

    $count = await(qb('users')->where('email', 'cancel@test.com')->count());
    expect($count)->toBe(1);
});

test('aggregate builder methods propagate cancellation to the driver', function () {
    $sleepFunc = ClientFactory::driver() === 'pgsql'
        ? 'pg_sleep(2)'
        : 'SLEEP(2)';

    $promise = qb('users')->count($sleepFunc);

    Loop::addTimer(0.1, fn() => $promise->cancel());

    try {
        await($promise);
        $this->fail('Promise should have been cancelled');
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(CancelledException::class);
    }

    $recovery = await(qb('users')->raw('SELECT 1 AS ok'));
    expect($recovery[0]->ok)->toBe(1);
});
