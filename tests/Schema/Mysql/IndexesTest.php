<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Indexes', function () {
    it('creates a table with primary key', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with unique index', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username');
            $table->unique('username');
        })->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with regular index', function () {
        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug');
            $table->index('slug');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with composite index', function () {
        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('slug');
            $table->index(['user_id', 'slug']);
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with fulltext index', function () {
        schema()->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->fullText();
            $table->fullText(['title', 'content']);
        })->wait();

        $exists = schema()->hasTable('articles')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates table with various spatial types', function () {
        schema()->create('geo_test', function (Blueprint $table) {
            $table->id();
            $table->point('location')->spatialIndex();
            $table->lineString('route')->nullable();
            $table->polygon('area')->spatialIndex();
            $table->geometry('shape')->nullable();
        })->wait();

        $exists = schema()->hasTable('geo_test')->wait();
        expect($exists)->toBeTruthy();

        schema()->dropIfExists('geo_test')->wait();
    });

    it('creates table with SRID specification', function () {
        schema()->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->point('location');
            $table->spatialIndex('location');
        })->wait();

        $exists = schema()->hasTable('stores')->wait();
        expect($exists)->toBeTruthy();

        schema()->dropIfExists('stores')->wait();
    });

    it('creates a table with named indexes', function () {
        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', 'custom_slug_index');
            $table->unique('slug', 'custom_slug_unique');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with index algorithms', function () {
        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', null, 'BTREE');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });
});
