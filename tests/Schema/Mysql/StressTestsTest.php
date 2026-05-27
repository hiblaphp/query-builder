<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";

            schema()->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            })->wait();

            $exists = schema()->hasTable($tableName)->wait();
            expect($exists)->toBeTruthy();

            schema()->drop($tableName)->wait();

            $exists = schema()->hasTable($tableName)->wait();
            expect($exists)->toBeFalsy();
        }
    });

    it('handles multiple alterations in sequence', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        })->wait();

        schema()->table('users', function (Blueprint $table) {
            $table->index('email');
        })->wait();

        schema()->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->wait();

        schema()->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        })->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });
});
