<?php

declare(strict_types=1);

use Hibla\Sql\Exceptions\DeadlockException;
use Hibla\Sql\TransactionOptions;
use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('transaction callback commits on success', function () {
    await(newQb()->transaction(function ($trx) {
        return $trx->from('users')->insert([
            'name' => 'Alice',
            'email' => 'alice@test.com',
        ]);
    }));

    expect(await(qb('users')->count()))->toBe(1);
});

test('transaction rolls back automatically when callback throws', function () {
    try {
        await(newQb()->transaction(function ($trx) {
            await($trx->from('users')->insert([
                'name' => 'Alice',
                'email' => 'alice@test.com',
            ]));

            throw new RuntimeException('Forced rollback');
        }));
    } catch (RuntimeException) {
        // expected
    }

    expect(await(qb('users')->count()))->toBe(0);
});

test('manual beginTransaction and commit persists rows', function () {
    $trx = await(newQb()->beginTransaction());

    await($trx->from('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
    ]));

    await($trx->commit());

    expect(await(qb('users')->count()))->toBe(1);
});

test('manual beginTransaction and rollback discards rows', function () {
    $trx = await(newQb()->beginTransaction());

    await($trx->from('users')->insert([
        'name' => 'Bob',
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
        ->and(await(qb('users')->value('name')))->toBe('Alice')
    ;
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
        'name' => 'Carol',
        'email' => 'carol@test.com',
    ]));

    await($trx->commit());

    expect(await(qb('users')->count()))->toBe(1);
});

test('lockForUpdate executes successfully inside an auto-managed transaction', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'a@test.com']]);

    $result = await(newQb()->transaction(function ($trx) {
        return await($trx->from('users')
            ->where('name', 'Alice')
            ->lockForUpdate()
            ->first());
    }));

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Alice')
    ;
});

test('lockForShare executes successfully inside a manual transaction', function () {
    TestSchema::insertUsers(client(), [['name' => 'Bob', 'email' => 'b@test.com']]);

    $trx = await(newQb()->beginTransaction());

    try {
        $result = await($trx->from('users')
            ->where('name', 'Bob')
            ->lockForShare()
            ->first());

        await($trx->commit());

        expect($result)->not->toBeNull()
            ->and($result->name)->toBe('Bob')
        ;
    } catch (Throwable $e) {
        await($trx->rollback());

        throw $e;
    }
});

test('lockForUpdate with skipLocked modifier executes without syntax errors', function () {
    TestSchema::insertUsers(client(), [['name' => 'Charlie', 'email' => 'c@test.com']]);

    $result = await(newQb()->transaction(function ($trx) {
        return await($trx->from('users')
            ->where('name', 'Charlie')
            ->lockForUpdate()
            ->skipLocked()
            ->first());
    }));

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Charlie')
    ;
});

test('lockForUpdate with noWait modifier executes without syntax errors', function () {
    TestSchema::insertUsers(client(), [['name' => 'Dave', 'email' => 'd@test.com']]);

    $result = await(newQb()->transaction(function ($trx) {
        return await($trx->from('users')
            ->where('name', 'Dave')
            ->lockForUpdate()
            ->noWait()
            ->first());
    }));

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Dave')
    ;
});

test('transaction auto-retries on DeadlockException and succeeds on subsequent attempt', function () {
    $attempts = 0;
    $options = new TransactionOptions(attempts: 3);

    await(newQb()->transaction(function ($trx) use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new DeadlockException('Deadlock mock');
        }

        await($trx->from('users')->insert([
            'name' => 'RetrySuccess',
            'email' => 'retry@test.com',
        ]));
    }, $options));

    expect($attempts)->toBe(2);

    $count = await(qb('users')->where('email', 'retry@test.com')->count());
    expect($count)->toBe(1);
});

test('transaction does not retry on non-retryable exceptions', function () {
    $attempts = 0;
    $options = new TransactionOptions(attempts: 3);

    try {
        await(newQb()->transaction(function ($trx) use (&$attempts) {
            $attempts++;

            throw new RuntimeException('Permanent application failure');
        }, $options));
        test()->fail('Should have thrown an exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Permanent application failure');
    }

    expect($attempts)->toBe(1);
});

test('transaction throws the final exception when retry attempts are exhausted', function () {
    $attempts = 0;
    $options = new TransactionOptions(attempts: 3);

    try {
        await(newQb()->transaction(function ($trx) use (&$attempts) {
            $attempts++;

            throw new DeadlockException('Deadlock mock ' . $attempts);
        }, $options));
        test()->fail('Should have thrown an exception');
    } catch (DeadlockException $e) {
        expect($e->getMessage())->toBe('Deadlock mock 3');
    }

    expect($attempts)->toBe(3);
});

test('nested transaction commits successfully alongside outer transaction', function () {
    await(newQb()->transaction(function ($outerTx) {
        await($outerTx->from('users')->insert(['name' => 'Outer', 'email' => 'outer@test.com']));

        await($outerTx->transaction(function ($nestedTx) {
            await($nestedTx->from('users')->insert(['name' => 'Nested', 'email' => 'nested@test.com']));
        }));
    }));

    $users = await(qb('users')->orderBy('id')->pluck('name'));

    expect($users)->toHaveCount(2)
        ->and($users)->toContain('Outer', 'Nested')
    ;
});

test('nested transaction rolls back to savepoint without aborting outer transaction', function () {
    await(newQb()->transaction(function ($outerTx) {
        await($outerTx->from('users')->insert(['name' => 'Outer 1', 'email' => 'outer1@test.com']));

        try {
            await($outerTx->transaction(function ($nestedTx) {
                await($nestedTx->from('users')->insert(['name' => 'Nested', 'email' => 'nested@test.com']));

                throw new RuntimeException('Nested operation failed');
            }));
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toBe('Nested operation failed');
        }

        await($outerTx->from('users')->insert(['name' => 'Outer 2', 'email' => 'outer2@test.com']));
    }));

    $users = await(qb('users')->orderBy('id')->pluck('name'));

    expect($users)->toHaveCount(2)
        ->and($users)->toContain('Outer 1', 'Outer 2')
        ->and($users)->not->toContain('Nested')
    ;
});

test('outer transaction rollback discards successful nested transactions', function () {
    try {
        await(newQb()->transaction(function ($outerTx) {
            await($outerTx->from('users')->insert(['name' => 'Outer', 'email' => 'outer@test.com']));

            await($outerTx->transaction(function ($nestedTx) {
                await($nestedTx->from('users')->insert(['name' => 'Nested', 'email' => 'nested@test.com']));
            }));

            throw new RuntimeException('Outer operation failed');
        }));
    } catch (RuntimeException) {
        // expected
    }

    expect(await(qb('users')->count()))->toBe(0);
});

test('deeply nested transactions handle isolated rollbacks correctly', function () {
    await(newQb()->transaction(function ($l1) {
        await($l1->from('users')->insert(['name' => 'Level 1', 'email' => 'l1@test.com']));

        await($l1->transaction(function ($l2) {
            await($l2->from('users')->insert(['name' => 'Level 2', 'email' => 'l2@test.com']));

            try {
                await($l2->transaction(function ($l3) {
                    await($l3->from('users')->insert(['name' => 'Level 3', 'email' => 'l3@test.com']));

                    throw new RuntimeException('Level 3 failed');
                }));
            } catch (RuntimeException) {
                // Ignore L3 failure
            }
        }));
    }));

    $users = await(qb('users')->orderBy('id')->pluck('name'));

    expect($users)->toHaveCount(2)
        ->and($users)->toContain('Level 1', 'Level 2')
        ->and($users)->not->toContain('Level 3')
    ;
});
