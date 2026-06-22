<?php

declare(strict_types=1);

namespace Tests\Internals;

use Hibla\QueryBuilder\Exceptions\QueryBuilderException;
use Hibla\QueryBuilder\Internals\DatabaseConnection;
use Hibla\QueryBuilder\Utilities\ConnectionFactory;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\await;

test('TransactionalQueryBuilder supports manual savepoints and rollbackTo', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);

    $conn = new DatabaseConnection($client);

    try {
        $tx = await($conn->beginTransaction());

        await($tx->from('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com']));

        await($tx->savepoint('sp_test'));

        await($tx->from('users')->insert(['name' => 'Bob', 'email' => 'bob@test.com']));
        await($tx->rollbackTo('sp_test'));

        await($tx->commit());

        $users = await($conn->table('users')->orderBy('id')->pluck('name'));

        expect($users)->toHaveCount(1)
            ->and($users)->toContain('Alice')
            ->and($users)->not->toContain('Bob')
        ;
    } finally {
        $conn->close();
    }
});

test('TransactionalQueryBuilder triggers onCommit and onRollback callbacks', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);

    $conn = new DatabaseConnection($client);

    try {
        $commitFired = false;
        $tx1 = await($conn->beginTransaction());
        $tx1->onCommit(function () use (&$commitFired) {
            $commitFired = true;
        });
        await($tx1->commit());
        expect($commitFired)->toBeTrue();

        $rollbackFired = false;
        $tx2 = await($conn->beginTransaction());
        $tx2->onRollback(function () use (&$rollbackFired) {
            $rollbackFired = true;
        });

        await($tx2->rollback());
        expect($rollbackFired)->toBeTrue();
    } finally {
        $conn->close();
    }
});

test('TransactionalQueryBuilder nested auto-transaction handles isolated failure', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    TestSchema::truncateAll($client);

    $conn = new DatabaseConnection($client);

    try {
        $tx = await($conn->beginTransaction());

        await($tx->from('users')->insert(['name' => 'L1', 'email' => 'l1@test.com']));

        try {
            await($tx->transaction(function ($nested) {
                await($nested->from('users')->insert(['name' => 'L2', 'email' => 'l2@test.com']));

                throw new \RuntimeException('Failed nested transaction');
            }));
        } catch (\RuntimeException $e) {
            expect($e->getMessage())->toBe('Failed nested transaction');
        }

        await($tx->commit());

        $users = await($conn->table('users')->pluck('name'));
        expect($users)->toHaveCount(1)
            ->and($users)->toContain('L1')
            ->and($users)->not->toContain('L2')
        ;
    } finally {
        $conn->close();
    }
});

test('TransactionalQueryBuilder beginTransaction is blocked and throws QueryBuilderException', function () {
    $client = ConnectionFactory::make(ClientFactory::config());
    $conn = new DatabaseConnection($client);

    try {
        $tx = await($conn->beginTransaction());

        expect(fn () => await($tx->beginTransaction()))
            ->toThrow(QueryBuilderException::class, 'Cannot begin a manual transaction')
        ;

        await($tx->rollback());
    } finally {
        $conn->close();
    }
});
