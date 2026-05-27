<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Foreign Keys', function () {
    it('creates tables with foreign key constraint', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with cascade on delete', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with cascade on update', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate();
            $table->string('title');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with custom reference', function () {
        schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with various actions', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
            ;
        })->wait();

        $exists = schema()->hasTable('profiles')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with null on delete', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with restrict actions', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('title');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with no action', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->noActionOnDelete()->noActionOnUpdate();
            $table->string('title');
        })->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });
});
