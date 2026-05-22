<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});

describe('Table Modification', function () {
    it('adds columns to existing table', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->integer('age')->default(0);
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('drops columns from table', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age');
        })->wait();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'age']);
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('drops single column from table', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->wait();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->dropColumn('email');
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('renames a column', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('modifies column type', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        })->wait();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->modifyString('name', 200)->nullable();
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('modifies integer column', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('age');
        })->wait();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->modifyInteger('age', true)->nullable();
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('modifies various column types', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('count');
            $table->text('bio');
            $table->boolean('active');
        })->wait();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->modifyBigInteger('count', true);
            $table->modifyText('bio');
            $table->modifyBoolean('active');
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('adds index to existing table', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
        })->wait();

        schema('sqlite')->table('posts', function (Blueprint $table) {
            $table->index('slug');
            $table->unique('title');
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('drops index from table', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
        })->wait();

        schema('sqlite')->table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_title_index');
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('drops unique index from table', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
        })->wait();

        schema('sqlite')->table('posts', function (Blueprint $table) {
            $table->dropUnique('posts_slug_unique');
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('drops primary key from table', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->string('slug');
            $table->primary('slug');
        })->wait();

        schema('sqlite')->table('posts', function (Blueprint $table) {
            $table->dropPrimary();
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });

    it('drops foreign key from table', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->wait();

        schema('sqlite')->table('posts', function (Blueprint $table) {
            $table->dropForeign('posts_user_id_foreign');
        })->wait();

        $exists = schema('sqlite')->hasTable('posts')->wait();
        expect($exists)->toBeTruthy();
    });
});
