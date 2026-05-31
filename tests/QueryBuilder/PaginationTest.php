<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Interfaces\Pagination\CursorPaginatorInterface;
use Hibla\QueryBuilder\Interfaces\Pagination\PaginatorInterface;
use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
    unset($_GET['page'], $_GET['cursor']);
});

afterEach(function () {
    unset($_GET['page'], $_GET['cursor']);
});


test('paginate returns a PaginatorInterface instance', function () {
    $paginator = await(qb('users')->paginate(15, 'http://localhost/users'));

    expect($paginator)->toBeInstanceOf(PaginatorInterface::class);
});

test('paginate reports correct total and per-page slice', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 25)
    ));

    $paginator = await(qb('users')->paginate(10, 'http://localhost/users'));

    expect($paginator->total())->toBe(25)
        ->and($paginator->items())->toHaveCount(10)
        ->and($paginator->lastPage())->toBe(3)
        ->and($paginator->currentPage())->toBe(1)
        ->and($paginator->hasMore())->toBeTrue()
        ->and($paginator->isFirstPage())->toBeTrue();
});

test('paginate on last page has no more results', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 5)
    ));

    $_GET['page'] = 1;
    $paginator    = await(qb('users')->paginate(10, 'http://localhost/users'));

    expect($paginator->hasMore())->toBeFalse()
        ->and($paginator->isLastPage())->toBeTrue();
});

test('paginate generates correct next and previous urls', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 30)
    ));

    $_GET['page'] = 2;
    $paginator    = await(qb('users')->paginate(10, 'http://localhost/users'));

    expect($paginator->nextPageUrl())->toContain('page=3')
        ->and($paginator->previousPageUrl())->toContain('page=1');
});

test('paginate returns empty items on empty table', function () {
    $paginator = await(qb('users')->paginate(10, 'http://localhost/users'));

    expect($paginator->total())->toBe(0)
        ->and($paginator->items())->toBeEmpty()
        ->and($paginator->hasPages())->toBeFalse();
});

test('cursorPaginate returns a CursorPaginatorInterface instance', function () {
    $paginator = await(qb('users')->cursorPaginate(15, 'id', 'http://localhost/users'));

    expect($paginator)->toBeInstanceOf(CursorPaginatorInterface::class);
});

test('cursorPaginate has next cursor when more rows exist', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 10)
    ));

    $paginator = await(qb('users')->cursorPaginate(5, 'id', 'http://localhost/users'));

    expect($paginator->hasMore())->toBeTrue()
        ->and($paginator->nextCursor())->not->toBeNull()
        ->and($paginator->items())->toHaveCount(5);
});

test('cursorPaginate has no next cursor on last page', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 3)
    ));

    $paginator = await(qb('users')->cursorPaginate(10, 'id', 'http://localhost/users'));

    expect($paginator->hasMore())->toBeFalse()
        ->and($paginator->nextCursor())->toBeNull()
        ->and($paginator->items())->toHaveCount(3);
});

test('cursorPaginate second page uses cursor from first page', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 10)
    ));

    $firstPage = await(qb('users')->cursorPaginate(5, 'id', 'http://localhost/users'));

    $_GET['cursor'] = $firstPage->nextCursor();
    $secondPage     = await(qb('users')->cursorPaginate(5, 'id', 'http://localhost/users'));

    expect($secondPage->items())->toHaveCount(5);

    $firstIds  = array_map(fn ($u) => $u->id, $firstPage->items());
    $secondIds = array_map(fn ($u) => $u->id, $secondPage->items());

    expect(array_intersect($firstIds, $secondIds))->toBeEmpty();
});