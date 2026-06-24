<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Interfaces\Pagination\CursorPaginatorInterface;
use Hibla\QueryBuilder\Interfaces\Pagination\PaginatorInterface;
use Tests\Fixtures\TestSchema;

use function Hibla\await;

beforeEach(function () {
    TestSchema::truncateAll(client());
    $_GET = []; // Reset the entire superglobal to prevent state bleeding between tests
});

afterEach(function () {
    $_GET = [];
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

    expect($paginator->total)->toBe(25)
        ->and($paginator->items)->toHaveCount(10)
        ->and($paginator->lastPage)->toBe(3)
        ->and($paginator->currentPage)->toBe(1)
        ->and($paginator->hasMore)->toBeTrue()
        ->and($paginator->isFirstPage)->toBeTrue()
    ;
});

test('paginate on last page has no more results', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 5)
    ));

    $_GET['page'] = 1;
    $paginator = await(qb('users')->paginate(10, 'http://localhost/users'));

    expect($paginator->hasMore)->toBeFalse()
        ->and($paginator->isLastPage)->toBeTrue()
    ;
});

test('paginate generates correct next and previous urls', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 30)
    ));

    $_GET['page'] = 2;
    $paginator = await(qb('users')->paginate(10, 'http://localhost/users'));

    expect($paginator->nextPageUrl())->toContain('page=3')
        ->and($paginator->previousPageUrl())->toContain('page=1')
    ;
});

test('paginate returns empty items on empty table', function () {
    $paginator = await(qb('users')->paginate(10, 'http://localhost/users'));

    expect($paginator->total)->toBe(0)
        ->and($paginator->items)->toBeEmpty()
        ->and($paginator->hasPages)->toBeFalse()
    ;
});

test('paginator modifiers return cloned instances to preserve immutability', function () {
    TestSchema::insertUsers(client(), [['name' => 'Alice', 'email' => 'alice@test.com']]);

    $original = await(qb('users')->paginate(10, 'http://localhost/users'));

    $appended = $original->appends('sort', 'desc');
    $withQuery = $original->withQueryString();
    $fragmented = $original->fragment('results');

    expect($original)->not->toBe($appended)
        ->and($original)->not->toBe($withQuery)
        ->and($original)->not->toBe($fragmented)
    ;
});

test('paginate urls include appended query strings and fragments', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob', 'email' => 'bob@test.com'],
    ]);

    $paginator = await(qb('users')->paginate(1, 'http://localhost/users'))
        ->appends('sort', 'desc')
        ->appends(['filter' => 'active'])
        ->fragment('#users-table')
    ;

    $url = $paginator->nextPageUrl();

    expect($url)->toContain('sort=desc')
        ->and($url)->toContain('filter=active')
        ->and($url)->toContain('page=2')
        ->and($url)->toEndWith('#users-table')
    ;
});

test('paginate withQueryString merges current GET parameters excluding page and cursor', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob', 'email' => 'bob@test.com'],
    ]);

    $_GET['search'] = 'developer';
    $_GET['page'] = 1;
    $_GET['cursor'] = 'stale_cursor_data';

    $paginator = await(qb('users')->paginate(1, 'http://localhost/users'))->withQueryString();

    $url = $paginator->nextPageUrl();

    expect($url)->toContain('search=developer')
        ->and($url)->not->toContain('cursor=stale_cursor_data') // Should be excluded
        ->and($url)->toContain('page=2')
    ;
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

    expect($paginator->hasMore)->toBeTrue()
        ->and($paginator->nextCursor)->not->toBeNull()
        ->and($paginator->items)->toHaveCount(5)
    ;
});

test('cursorPaginate has no next cursor on last page', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 3)
    ));

    $paginator = await(qb('users')->cursorPaginate(10, 'id', 'http://localhost/users'));

    expect($paginator->hasMore)->toBeFalse()
        ->and($paginator->nextCursor)->toBeNull()
        ->and($paginator->items)->toHaveCount(3)
    ;
});

test('cursorPaginate second page uses cursor from first page', function () {
    TestSchema::insertUsers(client(), array_map(
        fn ($i) => ['name' => "User $i", 'email' => "user$i@test.com"],
        range(1, 10)
    ));

    $firstPage = await(qb('users')->cursorPaginate(5, 'id', 'http://localhost/users'));

    $_GET['cursor'] = $firstPage->nextCursor;
    $secondPage = await(qb('users')->cursorPaginate(5, 'id', 'http://localhost/users'));

    expect($secondPage->items)->toHaveCount(5);

    $firstIds = array_map(fn ($u) => $u->id, $firstPage->items);
    $secondIds = array_map(fn ($u) => $u->id, $secondPage->items);

    expect(array_intersect($firstIds, $secondIds))->toBeEmpty();
});

test('cursorPaginate supports multi-column tie-breakers to prevent skipped rows', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'score' => 100],
        ['name' => 'Bob', 'email' => 'bob@test.com', 'score' => 100],
        ['name' => 'Charlie', 'email' => 'charlie@test.com', 'score' => 100],
        ['name' => 'Dave', 'email' => 'dave@test.com', 'score' => 100],
        ['name' => 'Eve', 'email' => 'eve@test.com', 'score' => 100],
    ]);

    $page1 = await(qb('users')
        ->orderBy('score', 'asc')
        ->orderBy('id', 'asc')
        ->cursorPaginate(2, ['score' => 'asc', 'id' => 'asc'], 'http://localhost/users'));

    expect($page1->items)->toHaveCount(2)
        ->and($page1->items[0]->name)->toBe('Alice')
        ->and($page1->items[1]->name)->toBe('Bob')
        ->and($page1->hasMore)->toBeTrue()
    ;

    $_GET['cursor'] = $page1->nextCursor;

    $page2 = await(qb('users')
        ->orderBy('score', 'asc')
        ->orderBy('id', 'asc')
        ->cursorPaginate(2, ['score' => 'asc', 'id' => 'asc'], 'http://localhost/users'));

    expect($page2->items)->toHaveCount(2)
        ->and($page2->items[0]->name)->toBe('Charlie')
        ->and($page2->items[1]->name)->toBe('Dave')
        ->and($page2->hasMore)->toBeTrue()
    ;
});

test('cursorPaginate urls include appended query strings and fragments', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob', 'email' => 'bob@test.com'],
    ]);

    $paginator = await(qb('users')->cursorPaginate(1, 'id', 'http://localhost/users'))
        ->appends(['type' => 'admin'])
        ->fragment('list')
    ;

    $url = $paginator->nextPageUrl();

    expect($url)->toContain('type=admin')
        ->and($url)->toContain('cursor=')
        ->and($url)->toEndWith('#list')
    ;
});

test('cursorPaginate withQueryString merges current GET parameters excluding page and cursor', function () {
    TestSchema::insertUsers(client(), [
        ['name' => 'Alice', 'email' => 'alice@test.com'],
        ['name' => 'Bob', 'email' => 'bob@test.com'],
    ]);

    $_GET['q'] = 'query';
    $_GET['page'] = 5;

    $paginator = await(qb('users')->cursorPaginate(1, 'id', 'http://localhost/users'))->withQueryString();

    $url = $paginator->nextPageUrl();

    expect($url)->toContain('q=query')
        ->and($url)->not->toContain('page=5')
        ->and($url)->toContain('cursor=');
});
