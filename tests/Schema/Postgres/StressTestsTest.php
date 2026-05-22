<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";

            schema('pgsql')->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            })->wait();

            $exists = schema('pgsql')->hasTable($tableName)->wait();
            expect($exists)->toBeTruthy();

            schema('pgsql')->drop($tableName)->wait();

            $exists = schema('pgsql')->hasTable($tableName)->wait();
            expect($exists)->toBeFalsy();
        }
    });

    it('handles multiple alterations in sequence', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        })->wait();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->index('email');
        })->wait();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->wait();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        })->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });
});
