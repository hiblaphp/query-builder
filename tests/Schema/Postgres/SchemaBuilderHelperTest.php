<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->wait();

        schema('pgsql')->dropColumn('users', 'email')->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses renameColumn helper method', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->renameColumn('users', 'name', 'full_name')->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses dropIndex helper method', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->wait();

        schema('pgsql')->dropIndex('users', 'users_email_index')->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses dropForeign helper method', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->wait();

        schema('pgsql')->dropForeign('posts', 'posts_user_id_foreign')->wait();

        $exists = schema('pgsql')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });
});
