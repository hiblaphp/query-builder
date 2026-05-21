<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Utilities\NumericConverter;

describe('NumericConverter', function () {
    describe('convertValue', function () {
        it('converts numeric string to int', function () {
            expect(NumericConverter::convertValue('123'))->toBe(123)
                ->and(NumericConverter::convertValue('0'))->toBe(0)
                ->and(NumericConverter::convertValue('-456'))->toBe(-456)
            ;
        });

        it('converts numeric string to float', function () {
            expect(NumericConverter::convertValue('123.45'))->toBe(123.45)
                ->and(NumericConverter::convertValue('0.0'))->toBe(0.0)
                ->and(NumericConverter::convertValue('-456.78'))->toBe(-456.78)
            ;
        });

        it('does not convert non-numeric strings', function () {
            expect(NumericConverter::convertValue('hello'))->toBe('hello')
                ->and(NumericConverter::convertValue('123abc'))->toBe('123abc')
                ->and(NumericConverter::convertValue(''))->toBe('')
            ;
        });

        it('does not convert non-string values', function () {
            expect(NumericConverter::convertValue(123))->toBe(123)
                ->and(NumericConverter::convertValue(123.45))->toBe(123.45)
                ->and(NumericConverter::convertValue(null))->toBe(null)
                ->and(NumericConverter::convertValue(true))->toBe(true)
            ;
        });

        it('handles edge cases', function () {
            expect(NumericConverter::convertValue('0.0'))->toBe(0.0)
                ->and(NumericConverter::convertValue('1e10'))->toBe(10000000000.0)
                ->and(NumericConverter::convertValue('-0'))->toBe(0)
            ;
        });
    });

    describe('convertRowArray', function () {
        it('converts numeric strings in a row', function () {
            $row = [
                'id' => '1',
                'name' => 'John',
                'age' => '30',
                'balance' => '1234.56',
                'active' => 'yes',
            ];

            $result = NumericConverter::convertRowArray($row);

            expect($result['id'])->toBe(1)
                ->and($result['name'])->toBe('John')
                ->and($result['age'])->toBe(30)
                ->and($result['balance'])->toBe(1234.56)
                ->and($result['active'])->toBe('yes')
            ;
        });

        it('handles empty row', function () {
            $result = NumericConverter::convertRowArray([]);
            expect($result)->toBe([]);
        });

        it('handles row with no numeric strings', function () {
            $row = ['name' => 'John', 'email' => 'john@example.com'];
            $result = NumericConverter::convertRowArray($row);

            expect($result)->toBe($row);
        });

        it('handles row with pre-computed keys', function () {
            $row = ['id' => '1', 'name' => 'John', 'age' => '30'];
            $keys = ['id', 'name', 'age'];

            $result = NumericConverter::convertRowArray($row, $keys);

            expect($result['id'])->toBe(1)
                ->and($result['name'])->toBe('John')
                ->and($result['age'])->toBe(30)
            ;
        });
    });

    describe('convertResultSet', function () {
        it('converts numeric strings in multiple rows', function () {
            $results = [
                ['id' => '1', 'name' => 'John', 'age' => '30'],
                ['id' => '2', 'name' => 'Jane', 'age' => '25'],
                ['id' => '3', 'name' => 'Bob', 'age' => '35'],
            ];

            $converted = NumericConverter::convertResultSet($results);

            expect($converted[0]['id'])->toBe(1)
                ->and($converted[0]['age'])->toBe(30)
                ->and($converted[1]['id'])->toBe(2)
                ->and($converted[1]['age'])->toBe(25)
                ->and($converted[2]['id'])->toBe(3)
                ->and($converted[2]['age'])->toBe(35)
            ;
        });

        it('handles empty result set', function () {
            $result = NumericConverter::convertResultSet([]);
            expect($result)->toBe([]);
        });

        it('handles result set with mixed types', function () {
            $results = [
                ['id' => '1', 'price' => '19.99', 'name' => 'Product', 'count' => '100'],
                ['id' => '2', 'price' => '29.99', 'name' => 'Item', 'count' => '200'],
            ];

            $converted = NumericConverter::convertResultSet($results);

            expect($converted[0]['id'])->toBe(1)
                ->and($converted[0]['price'])->toBe(19.99)
                ->and($converted[0]['name'])->toBe('Product')
                ->and($converted[0]['count'])->toBe(100)
            ;
        });

        it('preserves non-numeric strings', function () {
            $results = [
                ['id' => '1', 'code' => 'ABC123', 'status' => 'active'],
            ];

            $converted = NumericConverter::convertResultSet($results);

            expect($converted[0]['id'])->toBe(1)
                ->and($converted[0]['code'])->toBe('ABC123')
                ->and($converted[0]['status'])->toBe('active')
            ;
        });
    });
});
