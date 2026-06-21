<?php

declare(strict_types=1);

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Exceptions\DatabaseConfigurationException;
use Hibla\QueryBuilder\Exceptions\InvalidConnectionConfigException;
use Hibla\QueryBuilder\Internals\DatabaseConnection;
use Hibla\QueryBuilder\Utilities\ConnectionFactory;
use Hibla\Sql\Exceptions\DeadlockException;
use Hibla\Sql\Exceptions\PreparedException;
use Hibla\Sql\Exceptions\TransactionException;
use Hibla\Sql\TransactionOptions;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\await;

test('DB::table successfully routes to default connection', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        await(DB::table('users')->insert(['name' => 'Facade', 'email' => 'facade@test.com']));

        $count = await(DB::table('users')->count());
        expect($count)->toBe(1);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('DB::rawMethods proxy to connection and execute successfully', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        await(DB::rawExecute('INSERT INTO users (name, email) VALUES (?, ?)', ['Raw', 'raw@test.com']));

        $name = await(DB::rawValue('SELECT name FROM users WHERE email = ?', ['raw@test.com']));
        $row = await(DB::rawFirst('SELECT * FROM users WHERE email = ?', ['raw@test.com']));
        $rows = await(DB::raw('SELECT * FROM users'));

        $rowName = is_object($row) ? $row->name : $row['name'];

        expect($name)->toBe('Raw')
            ->and($rowName)->toBe('Raw')
            ->and($rows)->toHaveCount(1)
        ;
    } finally {
        $client->close();
        DB::reset();
    }
});

test('DB::transaction runs transaction callback and commits successfully', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        await(DB::transaction(function ($trx) {
            await($trx->from('users')->insert(['name' => 'TxCommit', 'email' => 'tx@test.com']));
        }));

        $count = await(DB::table('users')->count());
        expect($count)->toBe(1);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('DB::beginTransaction allows manual commit flow', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        $tx = await(DB::beginTransaction());

        await($tx->from('users')->insert(['name' => 'ManualTx', 'email' => 'manual@test.com']));
        await($tx->commit());

        $count = await(DB::table('users')->count());
        expect($count)->toBe(1);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('DB::beginTransaction allows manual rollback flow', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        $tx = await(DB::beginTransaction());

        await($tx->from('users')->insert(['name' => 'Discarded', 'email' => 'manual@test.com']));
        await($tx->rollback());

        $count = await(DB::table('users')->count());
        expect($count)->toBe(0);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('DB::connection routes queries to independent registered connections', function () {
    DB::reset();
    $client1 = ConnectionFactory::make(ClientFactory::config());
    $client2 = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client1);

    DB::setSqlClient($client1, ClientFactory::driverEnum());

    $reportingConn = new DatabaseConnection($client2, ClientFactory::driverEnum()->value);
    DB::addConnection('reporting', $reportingConn);

    try {
        await(DB::connection('reporting')->table('users')->insert(['name' => 'SecUser', 'email' => 'sec@test.com']));

        $count = await(DB::table('users')->count());
        expect($count)->toBe(1);
    } finally {
        $client1->close();
        $client2->close();
        DB::reset();
    }
});

test('Accessing an unconfigured connection throws DatabaseConfigurationException', function () {
    DB::reset();
    expect(fn () => DB::connection('ghost_connection'))
        ->toThrow(DatabaseConfigurationException::class)
    ;
});

test('Overwriting a connection with the same name updates the registry correctly', function () {
    DB::reset();
    $client1 = ConnectionFactory::make(ClientFactory::config());
    $client2 = ConnectionFactory::make(ClientFactory::config());

    try {
        $conn1 = new DatabaseConnection($client1, ClientFactory::driverEnum()->value);
        $conn2 = new DatabaseConnection($client2, ClientFactory::driverEnum()->value);

        DB::addConnection('custom', $conn1);
        expect(DB::connection('custom'))->toBe($conn1);

        DB::addConnection('custom', $conn2);
        expect(DB::connection('custom'))->toBe($conn2);
    } finally {
        $client1->close();
        $client2->close();
        DB::reset();
    }
});

test('Removing a registered connection makes it inaccessible', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    $conn = new DatabaseConnection($client, ClientFactory::driverEnum()->value);

    try {
        DB::addConnection('temporary_conn', $conn);
        DB::removeConnection('temporary_conn');
        expect(fn () => DB::connection('temporary_conn'))->toThrow(DatabaseConfigurationException::class);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('Removing a non-existent connection is handled safely', function () {
    DB::reset();
    expect(fn () => DB::removeConnection('non_existent'))->not->toThrow(Throwable::class);
});

test('DB::table() auto-initializes from config file when Facade is reset', function () {
    DB::reset();
    $qb = DB::table('users');
    expect($qb)->toBeInstanceOf(Hibla\QueryBuilder\Interfaces\QueryBuilderInterface::class);
    DB::reset();
});

test('DB::connection() auto-initializes from config file when Facade is reset', function () {
    DB::reset();
    $conn = DB::connection();
    expect($conn)->toBeInstanceOf(Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface::class);
    DB::reset();
});

test('DBFacade safely handles consecutive resets', function () {
    DB::reset();
    DB::reset();
    expect(true)->toBeTrue();
});

test('Facade transaction rolls back automatically on callback exception', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        try {
            await(DB::transaction(function ($trx) {
                await($trx->from('users')->insert(['name' => 'FailingUser', 'email' => 'fail@test.com']));

                throw new RuntimeException('Forced transaction failure');
            }));
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toBe('Forced transaction failure');
        }

        $count = await(DB::table('users')->count());
        expect($count)->toBe(0);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('Facade transaction auto-retries on DeadlockException and succeeds', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        $attempts = 0;
        $options = new TransactionOptions(attempts: 3);

        await(DB::transaction(function ($trx) use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new DeadlockException('Deadlock mock');
            }

            await($trx->from('users')->insert(['name' => 'RetrySuccess', 'email' => 'retry@test.com']));
        }, $options));

        expect($attempts)->toBe(2);

        $count = await(DB::table('users')->where('email', 'retry@test.com')->count());
        expect($count)->toBe(1);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('Nested transaction via Facade executes successfully using savepoints', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        await(DB::transaction(function ($outer) {
            await($outer->from('users')->insert(['name' => 'Outer', 'email' => 'outer@test.com']));

            await($outer->transaction(function ($nested) {
                await($nested->from('users')->insert(['name' => 'Nested', 'email' => 'nested@test.com']));
            }));
        }));

        $count = await(DB::table('users')->count());
        expect($count)->toBe(2);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('Facade manual transaction throws TransactionException on closed connection', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        $tx = await(DB::beginTransaction());

        DB::connection()->getClient()->close();

        expect(fn () => await($tx->commit()))->toThrow(TransactionException::class);
    } finally {
        $client->close();
        DB::reset();
    }
});

test('DB::raw with missing named parameter values throws PreparedException', function () {
    DB::reset();
    $client = ConnectionFactory::make(ClientFactory::config());
    DB::setSqlClient($client, ClientFactory::driverEnum());

    try {
        expect(fn () => await(DB::raw('SELECT * FROM users WHERE email = :email', ['not_email' => 'val'])))
            ->toThrow(PreparedException::class, 'Missing value for named parameter: :email')
        ;
    } finally {
        $client->close();
        DB::reset();
    }
});

test('DB::resolveClientFromConfig throws exception on unsupported drivers', function () {
    DB::reset();
    expect(fn () => DB::resolveClientFromConfig(['driver' => 'oracle_db']))
        ->toThrow(InvalidConnectionConfigException::class)
    ;
});

test('Multiple connections maintain strict transaction state isolation', function () {
    if (ClientFactory::driverEnum() === Hibla\QueryBuilder\Enums\DatabaseDriver::Sqlite) {
        $this->markTestSkipped('SQLite uses database-level locks, so concurrent writing transactions will throw LockWaitTimeoutException.');

        return;
    }

    DB::reset();
    $client1 = ConnectionFactory::make(ClientFactory::config());
    $client2 = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client1);

    DB::setSqlClient($client1, ClientFactory::driverEnum());

    $conn2 = new DatabaseConnection($client2, ClientFactory::driverEnum()->value);
    DB::addConnection('reporting', $conn2);

    try {
        $txDefault = await(DB::beginTransaction());
        $txReporting = await(DB::connection('reporting')->beginTransaction());

        await($txDefault->from('users')->insert(['name' => 'Default Tx', 'email' => 'default@test.com']));
        await($txReporting->from('users')->insert(['name' => 'Reporting Tx', 'email' => 'reporting@test.com']));

        await($txDefault->rollback());
        await($txReporting->commit());

        $count = await(DB::table('users')->count());
        $user = await(DB::table('users')->first());

        expect($count)->toBe(1)
            ->and($user->name)->toBe('Reporting Tx')
        ;
    } finally {
        $client1->close();
        $client2->close();
        DB::reset();
    }
});

test('DB::close() closes specific or all connections synchronously', function () {
    DB::reset();
    $client1 = ConnectionFactory::make(ClientFactory::config());
    $client2 = ConnectionFactory::make(ClientFactory::config());

    DB::setSqlClient($client1, ClientFactory::driverEnum());

    $conn2 = new DatabaseConnection($client2, ClientFactory::driverEnum()->value);
    DB::addConnection('secondary', $conn2);

    try {
        DB::close('secondary');

        expect(fn () => DB::connection('secondary'))->toThrow(DatabaseConfigurationException::class);
        expect(fn () => await($client2->query('SELECT 1')))->toThrow(RuntimeException::class);
        expect(await(DB::table('users')->count()))->toBeInt();

        DB::close();

        expect(fn () => await($client1->query('SELECT 1')))->toThrow(RuntimeException::class);
    } finally {
        $client1->close();
        $client2->close();
        DB::reset();
    }
});

test('DB::closeAsync() closes specific or all connections asynchronously', function () {
    DB::reset();
    $client1 = ConnectionFactory::make(ClientFactory::config());
    $client2 = ConnectionFactory::make(ClientFactory::config());

    DB::setSqlClient($client1, ClientFactory::driverEnum());

    $conn2 = new DatabaseConnection($client2, ClientFactory::driverEnum()->value);
    DB::addConnection('secondary', $conn2);

    try {
        $promise = DB::closeAsync('secondary');

        expect($promise)->toBeInstanceOf(PromiseInterface::class);
        await($promise);
        expect(fn () => DB::connection('secondary'))->toThrow(DatabaseConfigurationException::class);
        expect(fn () => await($client2->query('SELECT 1')))->toThrow(RuntimeException::class);
        expect(await(DB::table('users')->count()))->toBeInt();

        await(DB::closeAsync());

        expect(fn () => await($client1->query('SELECT 1')))->toThrow(RuntimeException::class);
    } finally {
        $client1->close();
        $client2->close();
        DB::reset();
    }
});
