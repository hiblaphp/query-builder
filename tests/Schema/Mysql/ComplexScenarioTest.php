<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Complex Scenarios', function () {
    it('creates a complete blog schema', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        })->wait();

        schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        })->wait();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_published']);
            $table->fullText(['title', 'content']);
        })->wait();

        schema()->create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        })->wait();

        $usersExists = schema()->hasTable('users')->wait();
        $categoriesExists = schema()->hasTable('categories')->wait();
        $postsExists = schema()->hasTable('posts')->wait();
        $commentsExists = schema()->hasTable('comments')->wait();

        expect($usersExists)->toBeTruthy();
        expect($categoriesExists)->toBeTruthy();
        expect($postsExists)->toBeTruthy();
        expect($commentsExists)->toBeTruthy();
    });

    it('performs multiple alterations on a table', function () {
        schema()->dropIfExists('users')->wait();

        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('old_email');
            $table->integer('age');
        })->wait();

        schema()->table('users', function (Blueprint $table) {
            $table->renameColumn('old_email', 'email');
            $table->dropColumn('age');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique('email');
        })->wait();

        $exists = schema()->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });
});
