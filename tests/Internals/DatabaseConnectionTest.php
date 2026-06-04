<?php

declare(strict_types=1);

namespace Tests\Internals;

use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\Internals\DatabaseConnection;
use Hibla\QueryBuilder\Utilities\ConnectionFactory;
use RuntimeException;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\await;

test('DatabaseConnection returns correct driver metadata and raw client', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    $driver = ClientFactory::driver();

    $conn = new DatabaseConnection($client, $driver);

    try {
        expect($conn->getClient())->toBe($client)
            ->and($conn->getDriverName())->toBe($driver)
        ;
    } finally {
        $conn->close();
    }
});

test('DatabaseConnection successfully instantiates QueryBuilder with proper bindings', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    $conn = new DatabaseConnection($client, ClientFactory::driver());

    try {
        $qb = $conn->table('users');
        expect($qb)->toBeInstanceOf(QueryBuilderInterface::class);
    } finally {
        $conn->close();
    }
});

test('DatabaseConnection proxies raw database executions directly', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);

    $conn = new DatabaseConnection($client, ClientFactory::driver());

    try {
        await($conn->rawExecute('INSERT INTO users (name, email) VALUES (?, ?)', ['ConnectionUser', 'conn@test.com']));

        $name = await($conn->rawValue('SELECT name FROM users WHERE email = ?', ['conn@test.com']));
        $row = await($conn->rawFirst('SELECT * FROM users WHERE email = ?', ['conn@test.com']));
        $rows = await($conn->raw('SELECT * FROM users'));

        $rowName = \is_object($row) ? $row->name : $row['name'];

        expect($name)->toBe('ConnectionUser')
            ->and($rowName)->toBe('ConnectionUser')
            ->and($rows)->toHaveCount(1)
        ;
    } finally {
        $conn->close();
    }
});

test('DatabaseConnection handles auto-managed transactions', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);

    $conn = new DatabaseConnection($client, ClientFactory::driver());

    try {
        await($conn->transaction(function ($tx) {
            await($tx->from('users')->insert(['name' => 'TxConnUser', 'email' => 'conn_tx@test.com']));
        }));

        $count = await($conn->table('users')->count());
        expect($count)->toBe(1);
    } finally {
        $conn->close();
    }
});

test('DatabaseConnection handles manual transaction flows', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);

    $conn = new DatabaseConnection($client, ClientFactory::driver());

    try {
        $tx = await($conn->beginTransaction());
        await($tx->from('users')->insert(['name' => 'ManualTxUser', 'email' => 'manual@test.com']));
        await($tx->commit());

        $count = await($conn->table('users')->count());
        expect($count)->toBe(1);
    } finally {
        $conn->close();
    }
});

test('DatabaseConnection supports asynchronous closing', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    $conn = new DatabaseConnection($client, ClientFactory::driver());

    await($conn->closeAsync());

    expect(fn () => await($client->query('SELECT 1')))->toThrow(RuntimeException::class);
});
