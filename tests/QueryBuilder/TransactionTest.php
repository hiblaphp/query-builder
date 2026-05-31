<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('transaction callback commits on success', function () {
    await(newQb()->transaction(function ($trx) {
        return $trx->from('users')->insert([
            'name'  => 'Alice',
            'email' => 'alice@test.com',
        ]);
    }));

    expect(await(qb('users')->count()))->toBe(1);
});

test('transaction rolls back automatically when callback throws', function () {
    try {
        await(newQb()->transaction(function ($trx) {
            await($trx->from('users')->insert([
                'name'  => 'Alice',
                'email' => 'alice@test.com',
            ]));

            throw new \RuntimeException('Forced rollback');
        }));
    } catch (\RuntimeException) {
        // expected
    }

    expect(await(qb('users')->count()))->toBe(0);
});

test('manual beginTransaction and commit persists rows', function () {
    $trx = await(newQb()->beginTransaction());

    await($trx->from('users')->insert([
        'name'  => 'Bob',
        'email' => 'bob@test.com',
    ]));

    await($trx->commit());

    expect(await(qb('users')->count()))->toBe(1);
});

test('manual beginTransaction and rollback discards rows', function () {
    $trx = await(newQb()->beginTransaction());

    await($trx->from('users')->insert([
        'name'  => 'Bob',
        'email' => 'bob@test.com',
    ]));

    await($trx->rollback());

    expect(await(qb('users')->count()))->toBe(0);
});

test('savepoint allows partial rollback within a transaction', function () {
    $trx = await(newQb()->beginTransaction());

    await($trx->from('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com']));
    await($trx->savepoint('sp1'));
    await($trx->from('users')->insert(['name' => 'Bob', 'email' => 'bob@test.com']));
    await($trx->rollbackTo('sp1'));
    await($trx->commit());

    expect(await(qb('users')->count()))->toBe(1)
        ->and(await(qb('users')->value('name')))->toBe('Alice');
});

test('onCommit callback fires after successful commit', function () {
    $fired = false;

    $trx = await(newQb()->beginTransaction());
    $trx->onCommit(function () use (&$fired) {
        $fired = true;
    });

    await($trx->from('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com']));
    await($trx->commit());

    expect($fired)->toBeTrue();
});

test('onRollback callback fires after rollback', function () {
    $fired = false;

    $trx = await(newQb()->beginTransaction());
    $trx->onRollback(function () use (&$fired) {
        $fired = true;
    });

    await($trx->from('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com']));
    await($trx->rollback());

    expect($fired)->toBeTrue();
});

test('transacting binds an existing query builder to an active transaction', function () {
    $trx = await(newQb()->beginTransaction());

    await(qb('users')->transacting($trx)->insert([
        'name'  => 'Carol',
        'email' => 'carol@test.com',
    ]));

    await($trx->commit());

    expect(await(qb('users')->count()))->toBe(1);
});

test('nested transaction via transaction() inside active transaction uses savepoints', function () {
    $trx = await(newQb()->beginTransaction());

    await($trx->from('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com']));

    try {
        await($trx->transaction(function ($inner) {
            await($inner->from('users')->insert(['name' => 'Bob', 'email' => 'bob@test.com']));
            throw new \RuntimeException('Rollback inner');
        }));
    } catch (\RuntimeException) {
        // expected — only Bob should be rolled back
    }

    await($trx->commit());

    expect(await(qb('users')->count()))->toBe(1)
        ->and(await(qb('users')->value('name')))->toBe('Alice');
});