<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('mysql');
});

describe('Table Operations', function () {
    it('drops a table', function () {
        schema('pgsql')->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->drop('temp_table')->wait();

        $exists = schema('pgsql')->hasTable('temp_table')->wait();
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        schema('pgsql')->dropIfExists('nonexistent_table')->wait();

        $exists = schema('pgsql')->hasTable('nonexistent_table')->wait();
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        schema('pgsql')->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->rename('old_name', 'new_name')->wait();

        $oldExists = schema('pgsql')->hasTable('old_name')->wait();
        $newExists = schema('pgsql')->hasTable('new_name')->wait();

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        schema('pgsql')->dropIfExists('new_name')->wait();
    });

    it('checks if table exists', function () {
        $exists = schema('pgsql')->hasTable('nonexistent')->wait();
        expect($exists)->toBeFalsy();

        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
        })->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });
});
