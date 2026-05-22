<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Table Operations', function () {
    it('drops a table', function () {
        schema()->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->drop('temp_table')->wait();

        $exists = schema()->hasTable('temp_table')->wait();
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        schema()->dropIfExists('nonexistent_table')->wait();

        $exists = schema()->hasTable('nonexistent_table')->wait();
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        schema()->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->rename('old_name', 'new_name')->wait();

        $oldExists = schema()->hasTable('old_name')->wait();
        $newExists = schema()->hasTable('new_name')->wait();

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        schema()->dropIfExists('new_name')->wait();
    });

    it('checks if table exists', function () {
        $exists = schema()->hasTable('nonexistent')->wait();
        expect($exists)->toBeFalsy();

        schema()->create('users', function (Blueprint $table) {
            $table->id();
        })->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });
});
