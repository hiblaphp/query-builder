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


function traceLog(string $message): void
{
    $timestamp = date('H:i:s.v');
    fwrite(STDERR, "[{$timestamp}] {$message}\n");
}

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

beforeEach(function () {
    traceLog(">> Booting Test: Truncating tables...");
    TestSchema::truncateAll(ClientFactory::make());
    traceLog(">> Tables truncated.");
});

test('query promise cancellation propagates to the server and cleanly recovers the connection', function () {
    traceLog("--- START: Basic Query Cancellation ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $sleepQuery = getSleepQuery($driver, 5);
        $startTime = hrtime(true);

        traceLog("Sending sleep query (5s)...");
        $promise = $qb->raw($sleepQuery);

        Loop::addTimer(0.5, function () use ($promise) {
            traceLog("Timer fired! Calling cancel()...");
            $promise->cancel();
            traceLog("cancel() call returned.");
        });

        try {
            traceLog("Awaiting sleep promise...");
            await($promise);
            test()->fail('Promise should have been cancelled.');
        } catch (CancelledException $e) {
            traceLog("Caught CancelledException successfully.");
            expect($e->getMessage())->toContain('cancel');
        }

        $durationMs = (hrtime(true) - $startTime) / 1e6;
        traceLog("Duration MS: " . $durationMs);
        expect($durationMs)->toBeLessThan(4000);

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: Basic Query Cancellation ---");
    } finally {
        await($client->closeAsync());
    }
});

test('transaction promise cancellation aborts the running query and rolls back safely', function () {
    traceLog("--- START: Transaction Cancellation ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $sleepQuery = getSleepQuery($driver, 5);

        traceLog("Starting transaction...");
        $promise = $qb->transaction(function ($trx) use ($sleepQuery) {
            traceLog("Inserting 'CancelMe' inside transaction...");
            await($trx->from('users')->insert(['name' => 'CancelMe', 'email' => 'cancel@test.com']));
            traceLog("Sending sleep query inside transaction...");
            await($trx->raw($sleepQuery));
            traceLog("This should never print! Continuing inserts...");
            await($trx->from('users')->insert(['name' => 'NeverReaches', 'email' => 'never@test.com']));
        });

        Loop::addTimer(0.5, function () use ($promise) {
            traceLog("Timer fired! Cancelling transaction promise...");
            $promise->cancel();
        });

        try {
            traceLog("Awaiting transaction promise...");
            await($promise);
            test()->fail('Transaction promise should have been cancelled.');
        } catch (CancelledException $e) {
            traceLog("Caught CancelledException from transaction.");
            expect(true)->toBeTrue();
        }

        traceLog("Delaying briefly to ensure rollback completes...");
        await(delay(0.1));

        traceLog("Verifying table is empty...");
        $count = await($qb->from('users')->count());
        expect($count)->toBe(0);

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: Transaction Cancellation ---");
    } finally {
        await($client->closeAsync());
    }
});

test('stream cancellation propagates to the server and stops delivery', function () {
    traceLog("--- START: Stream Cancellation ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        traceLog("Inserting 100 rows...");
        $rows = array_map(fn ($i) => ['name' => "User $i", 'email' => "stream$i@test.com"], range(1, 100));
        await($qb->from('users')->insertBatch($rows));

        $count = 0;

        traceLog("Starting stream via each()...");
        $promise = $qb->from('users')->each(function ($row) use (&$count) {
            $count++;
            if ($count % 10 === 0) traceLog("Stream read {$count} rows...");
            await(delay(0.05));
        });

        Loop::addTimer(0.5, function () use ($promise) {
            traceLog("Timer fired! Cancelling stream promise...");
            $promise->cancel();
        });

        try {
            traceLog("Awaiting stream promise...");
            await($promise);
            test()->fail('Stream promise should have been cancelled.');
        } catch (CancelledException $e) {
            traceLog("Caught CancelledException from stream.");
            expect(true)->toBeTrue();
        }

        traceLog("Total rows processed: {$count}");
        expect($count)->toBeGreaterThan(0)->toBeLessThan(50);

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: Stream Cancellation ---");
    } finally {
        await($client->closeAsync());
    }
});

test('cancellation without server-side support drops the connection safely', function () {
    traceLog("--- START: No Server-Side Cancellation ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: false);

    try {
        $sleepQuery = getSleepQuery($driver, 3);
        
        traceLog("Sending sleep query (3s) on connection without active cancel...");
        $promise = $qb->raw($sleepQuery);

        $startTime = hrtime(true);

        Loop::addTimer(0.5, function () use ($promise) {
            traceLog("Timer fired! Cancelling un-cancellable promise...");
            $promise->cancel();
        });

        try {
            traceLog("Awaiting sleep promise...");
            await($promise);
            test()->fail('Promise should have been cancelled.');
        } catch (CancelledException $e) {
            traceLog("Caught CancelledException.");
            expect(true)->toBeTrue();
        }

        $durationMs = (hrtime(true) - $startTime) / 1e6;
        traceLog("Duration MS: " . $durationMs);

        if ($driver !== DatabaseDriver::Postgres) {
            expect($durationMs)->toBeLessThan(2500);
        }

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: No Server-Side Cancellation ---");
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside each() gracefully cancels the stream without throwing an exception', function () {
    traceLog("--- START: Implicit Cancellation via false (each) ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        traceLog("Inserting 100 rows...");
        $rows = array_map(fn ($i) => ['name' => "User $i", 'email' => "internal$i@test.com"], range(1, 100));
        await($qb->from('users')->insertBatch($rows));

        $count = 0;

        traceLog("Starting stream via each()...");
        $promise = $qb->from('users')->each(function ($row) use (&$count) {
            $count++;
            if ($count === 5) {
                traceLog("Returning false at row 5...");
                return false;
            }
        });

        traceLog("Awaiting stream promise...");
        await($promise);
        traceLog("Stream promise resolved normally.");

        expect($count)->toBe(5);

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: Implicit Cancellation via false (each) ---");
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside chunkStream() gracefully cancels the stream without throwing', function () {
    traceLog("--- START: Implicit Cancellation via false (chunkStream) ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        traceLog("Inserting 100 rows...");
        $rows = array_map(fn ($i) => ['name' => "User $i", 'email' => "chunk$i@test.com"], range(1, 100));
        await($qb->from('users')->insertBatch($rows));

        $processedRows = 0;
        $chunkCount = 0;

        traceLog("Starting chunkStream(10)...");
        $promise = $qb->from('users')->chunkStream(10, function (array $chunk) use (&$processedRows, &$chunkCount) {
            $chunkCount++;
            $processedRows += count($chunk);

            if ($chunkCount === 2) {
                traceLog("Returning false at chunk 2...");
                return false;
            }
        });

        traceLog("Awaiting chunkStream promise...");
        await($promise);
        traceLog("chunkStream resolved normally.");

        expect($chunkCount)->toBe(2)
            ->and($processedRows)->toBe(20)
        ;

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: Implicit Cancellation via false (chunkStream) ---");
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside chunk() gracefully halts pagination without throwing', function () {
    traceLog("--- START: Implicit Cancellation via false (chunk) ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        traceLog("Inserting 50 rows...");
        $rows = array_map(fn ($i) => ['name' => "User $i", 'email' => "pagechunk$i@test.com"], range(1, 50));
        await($qb->from('users')->insertBatch($rows));

        $processedRows = 0;
        $chunkCount = 0;

        traceLog("Starting chunk(10)...");
        $promise = $qb->from('users')->orderBy('id')->chunk(10, function (array $chunk) use (&$processedRows, &$chunkCount) {
            $chunkCount++;
            $processedRows += count($chunk);

            if ($chunkCount === 3) {
                traceLog("Returning false at chunk 3...");
                return false;
            }
        });

        traceLog("Awaiting chunk promise...");
        await($promise);
        traceLog("chunk resolved normally.");

        expect($chunkCount)->toBe(3)
            ->and($processedRows)->toBe(30)
        ;

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: Implicit Cancellation via false (chunk) ---");
    } finally {
        await($client->closeAsync());
    }
});

test('returning false inside chunkById() gracefully halts pagination without throwing', function () {
    traceLog("--- START: Implicit Cancellation via false (chunkById) ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        traceLog("Inserting 10 rows...");
        $rows = array_map(fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com", 'score' => 10], range(1, 10));
        await($qb->from('users')->insertBatch($rows));

        $count = 0;
        traceLog("Starting chunkById(3)...");
        $promise = $qb->from('users')->chunkById(3, function (array $batch) use (&$count) {
            $count += count($batch);
            if ($count >= 6) {
                traceLog("Returning false after 6 records...");
                return false;
            }
        });

        traceLog("Awaiting chunkById promise...");
        await($promise);
        traceLog("chunkById resolved normally.");

        expect($count)->toBe(6)
            ->and($promise->isCancelled())->toBeFalse()
        ;

        traceLog("Testing connection recovery...");
        $result = await($qb->raw('SELECT 1 as val'));
        $val = is_object($result[0]) ? $result[0]->val : $result[0]['val'];
        expect((int) $val)->toBe(1);
        traceLog("--- END: Implicit Cancellation via false (chunkById) ---");
    } finally {
        await($client->closeAsync());
    }
});

test('calling cancel multiple times is idempotent and does not break the pool', function () {
    traceLog("--- START: Idempotent Cancel ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        $sql = getSleepQuery($driver, 2);

        traceLog("Sending sleep query (2s)...");
        $promise = $qb->raw($sql);

        traceLog("Calling cancel() three times rapidly...");
        $promise->cancel();
        $promise->cancel();
        $promise->cancel();

        try {
            traceLog("Awaiting promise...");
            await($promise);
        } catch (CancelledException) {
            traceLog("Caught expected CancelledException.");
        }

        traceLog("Testing connection recovery...");
        $recovery = await($qb->raw('SELECT 1 AS ok'));
        $val = is_object($recovery[0]) ? $recovery[0]->ok : $recovery[0]['ok'];
        expect((int)$val)->toBe(1);
        traceLog("--- END: Idempotent Cancel ---");
    } finally {
        await($client->closeAsync());
    }
});

test('concurrent mass cancellation safely kills all queries and recovers the pool', function () {
    traceLog("--- START: Mass Cancellation ---");
    $config = ClientFactory::config();
    $config['enable_server_side_cancellation'] = true;
    $config['max_connections'] = 3;
    $config['min_connections'] = 3;

    $driver = ClientFactory::driverEnum();
    $client = $driver === DatabaseDriver::Postgres ? new PostgresClient($config) : new MysqlClient($config);
    $qb = new QueryBuilder($client, $driver);

    try {
        $sql = getSleepQuery($driver, 5);

        traceLog("Sending 3 concurrent sleep queries...");
        $promises = [
            $qb->raw($sql),
            $qb->raw($sql),
            $qb->raw($sql),
        ];

        Loop::addTimer(0.5, function () use ($promises) {
            traceLog("Timer fired! Cancelling all 3 promises...");
            foreach ($promises as $promise) {
                $promise->cancel();
            }
        });

        foreach ($promises as $i => $promise) {
            try {
                traceLog("Awaiting promise #{$i}...");
                await($promise);
            } catch (CancelledException $e) {
                traceLog("Promise #{$i} caught CancelledException.");
            }
        }

        traceLog("Testing connection recovery with 3 concurrent SELECT 1...");
        $recoveries = await(Promise::all([
            $qb->raw('SELECT 1'),
            $qb->raw('SELECT 1'),
            $qb->raw('SELECT 1'),
        ]));

        expect($recoveries)->toHaveCount(3);
        traceLog("--- END: Mass Cancellation ---");
    } finally {
        await($client->closeAsync());
    }
});

test('commit and rollback promises are uninterruptible', function () {
    traceLog("--- START: Uninterruptible Transaction ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        traceLog("Beginning transaction...");
        $tx = await($qb->beginTransaction());

        traceLog("Inserting 'Cancel Test'...");
        await($tx->from('users')->insert(['name' => 'Cancel Test', 'email' => 'cancel@test.com']));

        traceLog("Calling commit() and cancelling its promise immediately...");
        $commitPromise = $tx->commit();
        $commitPromise->cancel();

        try {
            traceLog("Awaiting commit promise...");
            await($commitPromise);
        } catch (CancelledException $e) {
            traceLog("Caught expected CancelledException on commit.");
        }

        traceLog("Delaying briefly to ensure DB resolves commit state...");
        await(delay(0.1));

        traceLog("Verifying insertion succeeded despite commit promise cancellation...");
        $count = await($qb->from('users')->where('email', 'cancel@test.com')->count());
        expect($count)->toBe(1);
        traceLog("--- END: Uninterruptible Transaction ---");
    } finally {
        await($client->closeAsync());
    }
});

test('aggregate builder methods propagate cancellation to the driver', function () {
    traceLog("--- START: Aggregate Cancellation ---");
    [$client, $qb, $driver] = createCancellationClient(enableServerSideCancellation: true);

    try {
        traceLog("Inserting 'Sleep' user...");
        await($qb->from('users')->insert(['name' => 'Sleep', 'email' => 'sleep@test.com']));

        $sleepClause = $driver === DatabaseDriver::Postgres ? 'pg_sleep(3) IS NOT NULL' : 'SLEEP(3) = 0';

        traceLog("Sending COUNT query with sleep clause...");
        $promise = $qb->from('users')->whereRaw($sleepClause)->count();

        Loop::addTimer(0.5, function () use ($promise) {
            traceLog("Timer fired! Cancelling aggregate promise...");
            $promise->cancel();
        });

        try {
            traceLog("Awaiting aggregate promise...");
            await($promise);
            test()->fail('Promise should have been cancelled');
        } catch (\Throwable $e) {
            traceLog("Caught CancelledException on aggregate.");
            expect($e)->toBeInstanceOf(CancelledException::class);
        }

        traceLog("Testing connection recovery...");
        $recovery = await($qb->raw('SELECT 1 AS ok'));
        $val = is_object($recovery[0]) ? $recovery[0]->ok : $recovery[0]['ok'];
        expect((int)$val)->toBe(1);
        traceLog("--- END: Aggregate Cancellation ---");
    } finally {
        await($client->closeAsync());
    }
});