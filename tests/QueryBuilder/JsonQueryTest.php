<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('executes whereJson path queries successfully on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['preferences' => ['theme' => 'dark', 'notifications' => true]]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['preferences' => ['theme' => 'light', 'notifications' => false]]),
    ]));

    $result = await(qb('users')
        ->whereJson('meta->preferences->theme', 'dark')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes whereJsonContains array searches successfully on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['languages' => ['en', 'fr']]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['languages' => ['es', 'fr']]),
    ]));

    $result = await(qb('users')
        ->whereJsonContains('meta->languages', 'en')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes whereJsonLength array calculations successfully on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['tags' => ['admin', 'moderator', 'editor']]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['tags' => ['user']]),
    ]));

    $result = await(qb('users')
        ->whereJsonLength('meta->tags', '>', 2)
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes dot and arrow notation interchangeably on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['preferences' => ['theme' => 'dark']]),
    ]));

    $resultArrow = await(qb('users')->whereJson('meta->preferences->theme', 'dark')->get());
    $resultDot = await(qb('users')->whereJson('meta->preferences.theme', 'dark')->get());

    expect($resultArrow)->toHaveCount(1)
        ->and($resultDot)->toHaveCount(1)
        ->and($resultArrow[0]->name)->toBe('Alice')
        ->and($resultDot[0]->name)->toBe('Alice')
    ;
});

test('executes boolean literal containment checks on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['active' => true]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['active' => false]),
    ]));

    $result = await(qb('users')
        ->whereJsonContains('meta->active', true)
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes root-level JSON containment checks on real database', function () {
    if (ClientFactory::driverEnum() === DatabaseDriver::Sqlite) {
        $this->markTestSkipped('SQLite json_each does not natively support root-level object containment checks.');

        return;
    }

    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['status' => 'vip', 'rank' => 10]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['status' => 'regular', 'rank' => 2]),
    ]));

    $result = await(qb('users')
        ->whereJsonContains('meta', ['status' => 'vip'])
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes text partial-matching LIKE queries on JSON extracts', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['role' => 'administrator']),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['role' => 'moderator']),
    ]));

    $result = await(qb('users')
        ->whereJson('meta->role', 'LIKE', '%admin%')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('aligns parameters correctly with multiple consecutive JSON and standard conditions', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'status' => 'active',
        'meta' => json_encode(['preferences' => ['theme' => 'dark']]),
    ]));

    $result = await(qb('users')
        ->where('status', 'active')
        ->whereJson('meta->preferences->theme', 'dark')
        ->where('name', 'Alice')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Alice')
    ;
});

test('executes whereJsonDoesntContain negative array searches successfully on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['languages' => ['en', 'fr']]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['languages' => ['es', 'fr']]),
    ]));

    $result = await(qb('users')
        ->whereJsonDoesntContain('meta->languages', 'en')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Bob')
    ;
});

test('executes orWhereJsonDoesntContain logical OR searches on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['languages' => ['en', 'fr']]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['languages' => ['es', 'fr']]),
    ]));

    await(qb('users')->insert([
        'name' => 'Carol',
        'email' => 'carol@test.com',
        'meta' => json_encode(['languages' => ['en', 'es']]),
    ]));

    $result = await(qb('users')
        ->whereJsonDoesntContain('meta->languages', 'en')
        ->orWhereJsonDoesntContain('meta->languages', 'es')
        ->orderBy('name', 'ASC')
        ->get());

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Alice')
        ->and($result[1]->name)->toBe('Bob')
    ;
});

test('executes negative path comparison != on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['preferences' => ['theme' => 'dark']]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['preferences' => ['theme' => 'light']]),
    ]));

    $result = await(qb('users')
        ->whereJson('meta->preferences->theme', '!=', 'dark')
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Bob')
    ;
});

test('executes negative array length calculations != on real database', function () {
    await(qb('users')->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'meta' => json_encode(['tags' => ['admin', 'moderator']]),
    ]));

    await(qb('users')->insert([
        'name' => 'Bob',
        'email' => 'bob@test.com',
        'meta' => json_encode(['tags' => ['user']]),
    ]));

    $result = await(qb('users')
        ->whereJsonLength('meta->tags', '!=', 2)
        ->get());

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Bob')
    ;
});
