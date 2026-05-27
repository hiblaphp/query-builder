<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});

describe('Indexes', function () {
    it('creates a table with primary key', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with unique index', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username');
            $table->unique('username');
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with regular index', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug');
            $table->index('slug');
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with composite index', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('slug');
            $table->index(['user_id', 'slug']);
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with fulltext index', function () {
        schema('sqlite')->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->fullText();
            $table->fullText(['title', 'content']);
        })->wait();

        $exists = schema('sqlite')->hasTable('articles')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates table with various spatial types', function () {
        schema('sqlite')->create('geo_test', function (Blueprint $table) {
            $table->id();
            $table->point('location')->spatialIndex();
            $table->lineString('route')->nullable();
            $table->polygon('area')->spatialIndex();
            $table->geometry('shape')->nullable();
        })->wait();

        $exists = schema('sqlite')->hasTable('geo_test')->wait();
        expect($exists)->toBeTruthy();

        schema('sqlite')->dropIfExists('geo_test')->wait();
    });

    it('creates table with SRID specification', function () {
        schema('sqlite')->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->point('location');
            $table->spatialIndex('location');
        })->wait();

        $exists = schema('sqlite')->hasTable('stores')->wait();
        expect($exists)->toBeTruthy();

        schema('sqlite')->dropIfExists('stores')->wait();
    });

    it('creates a table with named indexes', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', 'custom_slug_index');
            $table->unique('slug', 'custom_slug_unique');
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with index algorithms', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', null, 'BTREE');
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });
});
