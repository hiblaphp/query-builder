<?php

declare(strict_types=1);

use Tests\Fixtures\TestSchema;

beforeEach(function () {
    TestSchema::truncateAll(client());
});

test('debug() outputs formatted query information and continues execution', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'alice@test.com']]);

    $query = qb('users')->where('name', 'Alice');

    ob_start();
    $returnedQuery = $query->debug();
    $output = ob_get_clean();

    $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

    expect($returnedQuery)->toBe($query);

    expect($cleanOutput)->toContain('Query Builder Debug')
        ->and($cleanOutput)->not->toContain('Query Builder Dump')
        ->and($cleanOutput)->not->toContain('dd')
    ;

    expect($cleanOutput)->toContain('SELECT * FROM users WHERE name = ?')
        ->and($cleanOutput)->toContain('Alice')
        ->and($cleanOutput)->toContain('(string)')
        ->and($cleanOutput)->toContain("SELECT * FROM users WHERE name = 'Alice'")
    ;
});

test('toRawSql() safely interpolates parameters for raw SQL output', function () {
    $query = qb('users')
        ->where('status', 'active')
        ->whereIn('id', [1, 2, 3])
        ->whereNull('deleted_at')
    ;

    $rawSql = $query->toRawSql();

    expect($rawSql)->toBe(
        "SELECT * FROM users WHERE status = 'active' AND id IN (1, 2, 3) AND deleted_at IS NULL"
    );
});

test('debug() handles queries with no bindings safely', function () {
    $query = qb('users');

    ob_start();
    $query->debug();
    $output = ob_get_clean();

    $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

    expect($cleanOutput)->toContain('Query Builder Debug')
        ->and($cleanOutput)->toContain('(no bindings)')
        ->and($cleanOutput)->toContain('SELECT * FROM users')
    ;
});
