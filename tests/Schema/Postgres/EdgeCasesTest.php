<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Edge Cases', function () {
    it('handles empty table creation', function () {
        schema('pgsql')->create('empty_table', function (Blueprint $table) {
            $table->id();
        })->wait();

        $exists = schema('pgsql')->hasTable('empty_table')->wait();
        expect($exists)->toBeTruthy();

        schema('pgsql')->dropIfExists('empty_table')->wait();
    });

    it('handles table with many columns', function () {
        schema('pgsql')->create('wide_table', function (Blueprint $table) {
            $table->id();
            for ($i = 1; $i <= 20; $i++) {
                $table->string("column_{$i}")->nullable();
            }
        })->wait();

        $exists = schema('pgsql')->hasTable('wide_table')->wait();
        expect($exists)->toBeTruthy();

        schema('pgsql')->dropIfExists('wide_table')->wait();
    });

    it('handles dropping non-existent table gracefully', function () {
        $result = schema('pgsql')->dropIfExists('this_table_does_not_exist')->wait();
        expect($result)->not->toThrow(Exception::class);
    });
});
