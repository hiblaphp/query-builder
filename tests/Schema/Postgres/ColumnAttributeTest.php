<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Column Attributes', function () {
    it('creates columns with nullable attribute', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable(false);
        })->wait();

        $exists = schema('pgsql')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with default values', function () {
        schema('pgsql')->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value')->default('default_value');
            $table->integer('count')->default(0);
            $table->boolean('active')->default(true);
            $table->decimal('amount', 10, 2)->default(0.00);
        })->wait();

        $exists = schema('pgsql')->hasTable('settings')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with unsigned attribute', function () {
        schema('pgsql')->create('counters', function (Blueprint $table) {
            $table->id();
            $table->integer('count')->unsigned();
            $table->bigInteger('big_count')->unsigned();
        })->wait();

        $exists = schema('pgsql')->hasTable('counters')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with useCurrent for timestamps', function () {
        schema('pgsql')->create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->onUpdate('CURRENT_TIMESTAMP');
        })->wait();

        $exists = schema('pgsql')->hasTable('logs')->wait();
        expect($exists)->toBeTruthy();
    });
});
