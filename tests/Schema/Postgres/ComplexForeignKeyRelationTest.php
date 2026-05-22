<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Complex Foreign Key Relationships', function () {
    it('creates multiple foreign keys on single table', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
        })->wait();

        $exists = schema('pgsql')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates self-referencing foreign key', function () {
        schema('pgsql')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        })->wait();

        $exists = schema('pgsql')->hasTable('categories')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates composite foreign key', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
        })->wait();

        schema('pgsql')->create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('bio');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        })->wait();

        $exists = schema('pgsql')->hasTable('user_profiles')->wait();
        expect($exists)->toBeTruthy();
    });
});
