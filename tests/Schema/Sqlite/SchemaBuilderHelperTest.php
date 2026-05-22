<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->wait();

        schema('sqlite')->dropColumn('users', 'email')->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses renameColumn helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('sqlite')->renameColumn('users', 'name', 'full_name')->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses dropIndex helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->wait();

        schema('sqlite')->dropIndex('users', 'users_email_index')->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses dropForeign helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->wait();

        schema('sqlite')->dropForeign('posts', 'posts_user_id_foreign')->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });
});
