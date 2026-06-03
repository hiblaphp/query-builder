<?php

declare(strict_types=1);

use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\SqlClientInterface;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

uses()
    ->beforeAll(function () {
        $client = ClientFactory::make();
        TestSchema::up($client, ClientFactory::driver());
    })
    ->afterAll(function () {
        $client = ClientFactory::make();
        TestSchema::down($client);
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
