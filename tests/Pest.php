<?php

declare(strict_types=1);

use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\SqlClientInterface;
use Rcalicdan\Defer\Defer;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

Defer::global(function () {
    $client = ClientFactory::make();
    TestSchema::down($client);
    $client->close();
    DB::close();

    if (ClientFactory::driverEnum() === DatabaseDriver::Sqlite) {
        $file = __DIR__ . '/../database.sqlite';
        $files = [$file, $file . '-wal', $file . '-shm'];

        foreach ($files as $f) {
            if (file_exists($f)) {
                for ($i = 1; $i <= 10; $i++) {
                    if (@unlink($f)) {
                        break;
                    }

                    usleep(100000);
                }
            }
        }
    }
});

uses()
    ->beforeAll(function () {
        $client = ClientFactory::make();
        TestSchema::up($client, ClientFactory::driver());
    })
    ->in(__DIR__)
;

function client(): SqlClientInterface
{
    return ClientFactory::make();
}

function qb(string $table): QueryBuilder
{
    return (new QueryBuilder(client(), ClientFactory::driverEnum()))->from($table);
}

function newQb(): QueryBuilder
{
    return new QueryBuilder(client(), ClientFactory::driverEnum());
}
