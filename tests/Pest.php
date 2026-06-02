<?php

declare(strict_types=1);

use Hibla\QueryBuilder\QueryBuilder;
use Hibla\Sql\SqlClientInterface;
use Tests\Fixtures\TestSchema;
use Tests\Helpers\ClientFactory;

uses()
    ->beforeAll(function () {
        fwrite(STDERR, "\n[PEST HOOK] >>> beforeAll START <<<\n");
        $client = ClientFactory::make();
        fwrite(STDERR, "[PEST HOOK] Running TestSchema::up...\n");
        TestSchema::up($client, ClientFactory::driver());
        fwrite(STDERR, "[PEST HOOK] >>> beforeAll END <<<\n\n");
    })
    ->afterAll(function () {
        fwrite(STDERR, "\n[PEST HOOK] >>> afterAll START (Teardown) <<<\n");
        $client = ClientFactory::make();
        fwrite(STDERR, "[PEST HOOK] Running TestSchema::down...\n");
        TestSchema::down($client);

        if (method_exists($client, 'close')) {
            fwrite(STDERR, "[PEST HOOK] Closing Database Connection Pool...\n");
            $client->close();
        }
        fwrite(STDERR, "[PEST HOOK] >>> afterAll END <<<\n\n");
    })
    ->in(__DIR__);

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