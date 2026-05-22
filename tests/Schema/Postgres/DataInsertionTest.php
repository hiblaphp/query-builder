<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Data Insertion and Verification', function () {
    it('creates table and inserts data', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->integer('age')->default(0);
        })->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (name, email, age) VALUES (?, ?, ?)',
            ['John Doe', 'john@example.com', 30]
        )->wait();

        $user = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM users WHERE email = ?', ['john@example.com'])->wait();

        expect($user)->not->toBeNull();
        expect($user['name'])->toBe('John Doe');
        expect($user['email'])->toBe('john@example.com');
        expect((int) $user['age'])->toBe(30);
    });

    it('respects default values', function () {
        schema('pgsql')->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
        })->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO products (name) VALUES (?)',
            ['Test Product']
        )->wait();

        $product = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM products WHERE name = ?', ['Test Product'])->wait();

        expect($product)->not->toBeNull();
        expect((float) $product['price'])->toBe(0.00);
        expect((int) $product['stock'])->toBe(0);
        expect((int) $product['active'])->toBe(1);

        schema('pgsql')->dropIfExists('products')->wait();
    });

    it('respects nullable constraints', function () {
        schema('pgsql')->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('bio')->nullable();
            $table->string('website')->nullable();
        })->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO profiles (bio) VALUES (?)',
            [null]
        )->wait();

        $profile = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM profiles ORDER BY id DESC LIMIT 1', [])->wait();

        expect($profile)->not->toBeNull();
        expect($profile['bio'])->toBeNull();
        expect($profile['website'])->toBeNull();

        schema('pgsql')->dropIfExists('profiles')->wait();
    });

    it('enforces unique constraints', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        })->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (email) VALUES (?)',
            ['test@example.com']
        )->wait();

        expect(function () {
            Hibla\QueryBuilder\DB::rawExecute(
                'INSERT INTO users (email) VALUES (?)',
                ['test@example.com']
            )->wait();
        })->toThrow(Exception::class);
    });

    it('enforces foreign key constraints', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['John Doe']
        )->wait();

        $userId = Hibla\QueryBuilder\DB::rawValue('SELECT id FROM users WHERE name = ?', ['John Doe'])->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Test Post']
        )->wait();

        $post = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM posts WHERE title = ?', ['Test Post'])->wait();
        expect($post)->not->toBeNull();
        expect((int) $post['user_id'])->toBe((int) $userId);

        expect(function () {
            Hibla\QueryBuilder\DB::rawExecute(
                'INSERT INTO posts (user_id, title) VALUES (?, ?)',
                [99999, 'Invalid Post']
            )->wait();
        })->toThrow(Exception::class);
    });

    it('cascades deletes correctly', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->wait();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['Jane Doe']
        )->wait();

        $userId = Hibla\QueryBuilder\DB::rawValue('SELECT id FROM users WHERE name = ?', ['Jane Doe'])->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 1']
        )->wait();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 2']
        )->wait();

        $postCount = Hibla\QueryBuilder\DB::rawValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId])->wait();
        expect((int) $postCount)->toBe(2);

        Hibla\QueryBuilder\DB::rawExecute('DELETE FROM users WHERE id = ?', [$userId])->wait();

        $postCount = Hibla\QueryBuilder\DB::rawValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId])->wait();
        expect((int) $postCount)->toBe(0);
    });
});
