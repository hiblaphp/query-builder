<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Table Creation', function () {
    it('creates a basic table', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        })->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with various column types', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content');
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        })->wait();

        $exists = schema('pgsql')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with auto-increment columns', function () {
        schema('pgsql')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->increments('legacy_id');
            $table->bigIncrements('big_id');
            $table->smallIncrements('small_id');
            $table->string('name');
        })->wait();

        $exists = schema('pgsql')->hasTable('categories')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with integer variations', function () {
        schema('pgsql')->create('stats', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('tiny_num');
            $table->smallInteger('small_num');
            $table->mediumInteger('medium_num');
            $table->integer('regular_num');
            $table->bigInteger('big_num');
            $table->unsignedTinyInteger('unsigned_tiny');
            $table->unsignedBigInteger('unsigned_big');
        })->wait();

        $exists = schema('pgsql')->hasTable('stats')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with text variations', function () {
        schema('pgsql')->create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('short_text');
            $table->mediumText('medium_text');
            $table->longText('long_text');
            $table->string('title', 100);
        })->wait();

        $exists = schema('pgsql')->hasTable('documents')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with decimal variations', function () {
        schema('pgsql')->create('financials', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->float('rate', 5, 2);
            $table->double('precise_value', 15, 8);
            $table->unsignedDecimal('positive_amount', 8, 2);
        })->wait();

        $exists = schema('pgsql')->hasTable('financials')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with date/time columns', function () {
        schema('pgsql')->create('events', function (Blueprint $table) {
            $table->id();
            $table->date('event_date');
            $table->dateTime('event_datetime');
            $table->timestamp('event_timestamp');
            $table->timestamps();
        })->wait();

        $exists = schema('pgsql')->hasTable('events')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with enum column', function () {
        schema('pgsql')->create('orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled']);
            $table->timestamps();
        })->wait();

        $exists = schema('pgsql')->hasTable('orders')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with soft deletes', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with comments', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('User full name');
            $table->string('email')->comment('User email address')->unique();
        })->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with after positioning', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->after('name');
            $table->timestamps();
        })->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });
});
