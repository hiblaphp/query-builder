# Hibla Query Builder

**A framework-agnostic, async-first query builder and connection pool manager for PHP 8.4+.**

> **Note:** This repository is the core query engine of the [Hibla Database Ecosystem](https://github.com/hiblaphp/database). 
> For complete, comprehensive documentation covering Migrations, Seeders, and advanced Query Builder features, please visit the main **[hiblaphp/database meta-package](https://github.com/hiblaphp/database)**.

## Overview

`hiblaphp/query-builder` provides high-performance, non-blocking database access for modern async PHP applications. It brings the familiar, highly-expressive fluent syntax of Laravel's query builder to asynchronous runtimes.

### Supported Drivers & Async Strategy
Rather than relying on blocking PDO drivers, Hibla utilizes tailored non-blocking drivers optimized for each database engine:

*   **SQLite** (Default): Achieved via a process-isolated async worker daemon pool (`hiblaphp/sqlite`), allowing asynchronous, non-blocking file access.
*   **MySQL & MariaDB**: Achieved via a pure PHP async socket implementation of the MySQL binary protocol (`hiblaphp/mysql`).
*   **PostgreSQL**: Achieved via native non-blocking socket-polling libpq drivers (`hiblaphp/postgres`).

## Features

*   **Native Connection Pooling**: Fully-managed connection pooling for all drivers (including subprocess pooling for SQLite).
*   **Zero-Config Default**: Defaulting to SQLite means your application can run immediately out-of-the-box with no external database server needed.
*   **Unbuffered Streaming**: Stream massive datasets row-by-row with automatic backpressure handling.
*   **Common Table Expressions**: Programmatic CTE support, including recursive sequences.
*   **Database-Specific Polyfills**: Dialect-specific support for JSON extraction, Pessimistic Locking (`lockForUpdate`), and Upserts across all supported engines.
*   **Immutable Query Building**: Abstract Syntax Trees (ASTs) are safely cloned on every modification, preventing query state from leaking across operations.

## Installation

> This package is currently in **beta**. Before installing, ensure your `composer.json`
> allows beta releases:

Install the package via Composer:

```bash
composer require hiblaphp/query-builder
```

Initialize your database configuration file:

```bash
cp vendor/hiblaphp/query-builder/hibla-database.php hibla-database.php
```

*(Edit `hibla-database.php` or your `.env` file to select your default connection).*

## Quick Start (Zero-Config SQLite)

Because the library defaults to an in-memory SQLite database, you can run queries immediately without configuring any external database servers:

```php
<?php

require 'vendor/autoload.php';

use Hibla\QueryBuilder\DB;
use function Hibla\await;

// 1. Setup your schema asynchronously
await(DB::rawExecute("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT)"));

// 2. Insert data
await(DB::table('users')->insert([
    'name' => 'Alice'
]));

// 3. Fetch data fluently
$user = await(DB::table('users')->where('name', 'Alice')->first());

echo "Hello, " . $user->name; // Outputs: Hello, Alice

// 4. Gracefully close connection pools when your application shuts down
DB::close();
```

> Note: You do not need to call DB::close() manually. the Query Builder will handle it for you after the query builder instance is destroyed or garbage collected. But you can still call it if you want to close the connection pool earlier or you want to be more explicit on resource management.

## Dependency Injection (Testable Architecture)

For enterprise applications and dependency injection purists, Hibla exposes clean interfaces. You do not have to rely on the static facade. You can bind `DatabaseConnectionInterface` in your DI container and inject it directly into your repositories:

```php
<?php

namespace App\Repositories;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use function Hibla\await;

class UserRepository
{
    public function __construct(
        private readonly DatabaseConnectionInterface $db
    ) {}

    /** @return PromiseInterface<list<array<string, mixed>>> */
    public function getActiveUsers(): PromiseInterface
    {
        return $this->db->table('users')
            ->where('status', 'active')
            ->latest()
            ->get();
    }

    public function createUser(array $data): PromiseInterface
    {
        return $this->db->table('users')->insertGetId($data);
    }
}
```
*Because your service depends on an interface rather than a static class, you can easily swap the real connection with an in-memory SQLite client during unit testing!*

## Immutable Architecture

A major feature of Hibla's Query Builder is that **every builder instance is strictly immutable**. 

Calling a method (like `where()`, `limit()`, or `select()`) *never* modifies the original object. Instead, it clones the AST (Abstract Syntax Tree) and returns a completely new instance. This allows you to safely build base queries and reuse them infinitely without state leaking across your application:

```php
$activeUsers = DB::table('users')->where('status', 'active');

// These two queries run independently and safely.
// The original $activeUsers instance is NEVER mutated!
$admins = await($activeUsers->where('role', 'admin')->get());
$guests = await($activeUsers->where('role', 'guest')->get());
```

## Testing & Development

Because Hibla tests live driver compilation and async socket execution, running the full test suite requires real databases to run against. A `docker-compose.yml` file is provided to spin up the necessary environments.

### 1. SQLite Testing (Zero Setup)
To run the SQLite test suite, no external database engines or Docker setups are required. Simply execute:
```bash
composer test:sqlite
```

### 2. MySQL & PostgreSQL Testing
Start the MySQL 8 and PostgreSQL 15 containers:
```bash
docker compose up -d
```

Run the tests against MySQL:
```bash
composer test:mysql
```

Run the tests against PostgreSQL:
```bash
composer test:pgsql
```

To run the entire suite across all three databases sequentially:
```bash
composer test:all
```

## Documentation

For full documentation on available query methods (Joins, Aggregates, JSON columns, CTEs, Cursor Pagination, and Pessimistic Locking), please read the **[Comprehensive Hibla Documentation](https://github.com/hiblaphp/database#query-builder)**.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).