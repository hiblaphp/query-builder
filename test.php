<?php

declare(strict_types=1);

use Hibla\Mysql\MysqlClient;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\QueryBuilder;

use function Hibla\await;

require __DIR__ . '/vendor/autoload.php';

$client = new MysqlClient(
    config: [
        'host' => '127.0.0.1',
        'port' => 3310,
        'database' => 'test',
        'username' => 'test_user',
        'password' => 'test_password',
    ],
    maxConnections: 10
);

$queryBuilder = new QueryBuilder($client);

$start = microtime(true);

$results = await(
    Promise::all([
        $queryBuilder->raw('SELECT SLEEP(1)'),
        $queryBuilder->raw('SELECT SLEEP(1)'),
        $queryBuilder->raw('SELECT SLEEP(1)'),
        $queryBuilder->raw('SELECT SLEEP(1)'),
        $queryBuilder->raw('SELECT SLEEP(1)'),
    ])
);

$end = microtime(true) - $start;
echo $end;
