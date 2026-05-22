<?php

use Hibla\Mysql\MysqlClient;
use Hibla\Promise\Promise;
use Hibla\QueryBuilder\QueryBuilder;

use function Hibla\await;

require __DIR__ . '/vendor/autoload.php';

$client =  new MysqlClient(
    config: [
        'host' => '127.0.0.1',
        'port' => 3309,
        'username' => 'root',
        'password' => 'Reymart1234',
        'database' => 'guitar_lyrics',
    ],
    maxConnections: 10
);

$queryBuilder = new QueryBuilder($client);

$start = microtime(true);

$results = await(
    Promise::all([
        $queryBuilder->raw(),
        $queryBuilder->raw(),
        $queryBuilder->raw(),
        $queryBuilder->raw(),
        $queryBuilder->raw(),
    ])
);

$end = microtime(true);
echo $end;
print_r($results);
