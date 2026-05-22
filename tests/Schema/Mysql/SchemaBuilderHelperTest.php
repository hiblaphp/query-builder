<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->wait();

        schema()->dropColumn('users', 'email')->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses renameColumn helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->renameColumn('users', 'name', 'full_name')->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses dropIndex helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->wait();

        schema()->dropIndex('users', 'users_email_index')->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('uses dropForeign helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->wait();

        schema()->dropForeign('posts', 'posts_user_id_foreign')->wait();

        $exists = schema()->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });
});
