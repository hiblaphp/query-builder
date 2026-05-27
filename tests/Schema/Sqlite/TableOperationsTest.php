<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});

describe('Table Operations', function () {
    it('drops a table', function () {
        schema('sqlite')->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('sqlite')->drop('temp_table')->wait();

        $exists = schema('sqlite')->hasTable('temp_table')->wait();
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        schema('sqlite')->dropIfExists('nonexistent_table')->wait();

        $exists = schema('sqlite')->hasTable('nonexistent_table')->wait();
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        schema('sqlite')->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('sqlite')->rename('old_name', 'new_name')->wait();

        $oldExists = schema('sqlite')->hasTable('old_name')->wait();
        $newExists = schema('sqlite')->hasTable('new_name')->wait();

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        schema('sqlite')->dropIfExists('new_name')->wait();
    });

    it('checks if table exists', function () {
        $exists = schema('sqlite')->hasTable('nonexistent')->wait();
        expect($exists)->toBeFalsy();

        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });
});
