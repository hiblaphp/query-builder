# Hibla Query Builder

**A framework-agnostic, async-first query builder and connection pool manager for PHP 8.4+.**

> **Note:** This repository is the core query engine of the [Hibla Database Ecosystem](https://github.com/hiblaphp/database). 
> For complete, comprehensive documentation covering Migrations, Seeders, and advanced Query Builder features, please visit the main **[hiblaphp/database meta-package](https://github.com/hiblaphp/database)**.

## Overview

`hiblaphp/query-builder` provides high-performance, non-blocking database access for modern async PHP applications (Swoole, RoadRunner, Workerman). It brings the familiar, highly-expressive fluent syntax of Laravel's query builder to asynchronous runtimes.

Features include native connection pooling, unbuffered streaming (`chunkStream`), JSON query abstractions, programmatic CTEs, and full server-side query cancellation.

## Installation

>This package is currently in **beta**. Before installing, ensure your `composer.json`
allows beta releases:

Install the package via Composer:

```bash
composer require hiblaphp/query-builder
```

Initialize your database configuration file:

```bash
cp vendor/hiblaphp/query-builder/hibla-database.php hibla-database.php
```

*(Edit `hibla-database.php` or your `.env` file to set your database credentials).*

## Quick Start

Every execution method returns a `PromiseInterface` and must be awaited to release the fiber and prevent event loop blocking.

### Using the Static Facade
For rapid development, you can use the static `DB` facade to execute queries anywhere in your application:

```php
<?php

require 'vendor/autoload.php';

use Hibla\QueryBuilder\DB;
use function Hibla\await;

// 1. Insert data asynchronously
await(DB::table('users')->insert([
    'name' => 'Alice',
    'email' => 'alice@example.com'
]));

// 2. Fetch the data fluently
$user = await(DB::table('users')->where('email', 'alice@example.com')->first());

echo "Hello, " . $user->name;

// 3. Gracefully close connection pools when your application shuts down
DB::close();
```

### Dependency Injection (Testable Architecture)
For enterprise applications and DI purists, Hibla exposes clean interfaces. You do not have to rely on the static facade. You can bind `DatabaseConnectionInterface` in your DI container and inject it directly into your services or repositories:

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
*Because your service depends on an interface rather than a static class, you can easily swap the real connection with a mock or an in-memory SQLite client during unit testing!*

## Testing & Development

Because Hibla tests live driver compilation and async socket execution, the test suite requires real databases to run against. A `docker-compose.yml` file is provided to quickly spin up the necessary environments.

### 1. Start the Database Containers
Start the MySQL 8 and PostgreSQL 15 containers (with `pgvector` pre-installed):
```bash
docker compose up -d
```

### 2. Run the Test Suite
The repository uses [Pest PHP](https://pestphp.com/) for testing. 

To run the tests against MySQL:
```bash
composer test:mysql
```

To run the tests against PostgreSQL:
```bash
composer test:pgsql
```

To run the tests against both databases sequentially:
```bash
composer test:all
```

## Documentation

For full documentation on available query methods (Joins, Aggregates, JSON columns, CTEs, Cursor Pagination, and Pessimistic Locking), please read the **[Comprehensive Hibla Documentation](https://github.com/hiblaphp/database#query-builder)**.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).