<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});

describe('Column Attributes', function () {
    it('creates columns with nullable attribute', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable(false);
        })->wait();

        $exists = schema('sqlite')->hasTable('users')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with default values', function () {
        schema('sqlite')->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value')->default('default_value');
            $table->integer('count')->default(0);
            $table->boolean('active')->default(true);
            $table->decimal('amount', 10, 2)->default(0.00);
        })->wait();

        $exists = schema('sqlite')->hasTable('settings')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with unsigned attribute', function () {
        schema('sqlite')->create('counters', function (Blueprint $table) {
            $table->id();
            $table->integer('count')->unsigned();
            $table->bigInteger('big_count')->unsigned();
        })->wait();

        $exists = schema('sqlite')->hasTable('counters')->wait();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with useCurrent for timestamps', function () {
        schema('sqlite')->create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->onUpdate('CURRENT_TIMESTAMP');
        })->wait();

        $exists = schema('sqlite')->hasTable('logs')->wait();
        expect($exists)->toBeTruthy();
    });
});
