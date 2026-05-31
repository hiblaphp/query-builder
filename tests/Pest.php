<?php

declare(strict_types=1);

use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\SqlClientInterface;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

uses()
    ->beforeAll(function () {
        TestSchema::up(ClientFactory::make(), ClientFactory::driver());
    })
    ->afterAll(function () {
        TestSchema::down(ClientFactory::make());
    });

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
