<?php

declare(strict_types=1);

namespace Tests\Internals;

use Hibla\QueryBuilder\Exceptions\DatabaseConfigurationException;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Internals\DatabaseConnection;
use Hibla\QueryBuilder\Internals\DatabaseManager;
use Hibla\QueryBuilder\Utilities\ConnectionFactory;
use RuntimeException;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\await;

test('DatabaseManager registers, retrieves, and falls back to default connection when none is specified', function () {
    $manager = new DatabaseManager();
    
    $client1 = ConnectionFactory::make(ClientFactory::config());
    $client2 = ConnectionFactory::make(ClientFactory::config());

    $conn1 = new DatabaseConnection($client1, ClientFactory::driver());
    $conn2 = new DatabaseConnection($client2, ClientFactory::driver());

    try {
        $manager->addConnection('conn_one', $conn1);
        $manager->addConnection('conn_two', $conn2);

        expect($manager->connection('conn_one'))->toBe($conn1)
            ->and($manager->connection('conn_two'))->toBe($conn2);

        expect($manager->connection())->toBe($conn1);
        $manager->setDefaultConnectionName('conn_two');
        expect($manager->getDefaultConnectionName())->toBe('conn_two');
        expect($manager->connection())->toBe($conn2);

        $manager->removeConnection('conn_two');
        expect($manager->getDefaultConnectionName())->toBeIn(['mysql', 'pgsql']);
        expect(fn() => $manager->connection('conn_two'))->toThrow(DatabaseConfigurationException::class);
    } finally {
        $client1->close();
        $client2->close();
        $manager->close();
    }
});

test('DatabaseManager lazily initializes default connections from configuration file', function () {
    $manager = new DatabaseManager();

    try {
        $defaultName = $manager->getDefaultConnectionName();
        expect($defaultName)->toBeIn(['mysql', 'pgsql']);
        $conn = $manager->connection($defaultName);
        expect($conn)->toBeInstanceOf(DatabaseConnectionInterface::class);
    } finally {
        $manager->close();
    }
});

test('DatabaseManager proxies direct query operations to the default connection', function () {
    $manager = new DatabaseManager();
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);

    $conn = new DatabaseConnection($client, ClientFactory::driver());
    $manager->addConnection('default', $conn);
    $manager->setDefaultConnectionName('default');

    try {
        await($manager->rawExecute('INSERT INTO users (name, email) VALUES (?, ?)', ['MgrProxy', 'mgr@test.com']));
        
        $count = await($manager->table('users')->count());
        $value = await($manager->rawValue('SELECT name FROM users WHERE email = ?', ['mgr@test.com']));

        expect($count)->toBe(1)
            ->and($value)->toBe('MgrProxy');
    } finally {
        $manager->close();
    }
});

test('DatabaseManager asynchronous closing cleans up all internal connections', function () {
    $manager = new DatabaseManager();
    
    $client1 = ConnectionFactory::make(ClientFactory::config());
    $client2 = ConnectionFactory::make(ClientFactory::config());

    $conn1 = new DatabaseConnection($client1, ClientFactory::driver());
    $conn2 = new DatabaseConnection($client2, ClientFactory::driver());

    $manager->addConnection('one', $conn1);
    $manager->addConnection('two', $conn2);

    await($manager->closeAsync());
    expect(fn() => await($client1->query('SELECT 1')))->toThrow(RuntimeException::class);
    expect(fn() => await($client2->query('SELECT 1')))->toThrow(RuntimeException::class);
});