<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Hibla\Mysql\MysqlClient;
use function Hibla\async;
use function Hibla\await;
use function Rcalicdan\ConfigLoader\env;

async(function() {
    // 1. Initialize your MysqlClient using your docker-compose credentials
    $client = new MysqlClient([
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'test_mysql'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
    ]);

    // Cleanup any lingering tables from previous runs
    await($client->query("DROP TABLE IF EXISTS test_ddl_rollback"));
    await($client->query("DROP TABLE IF EXISTS test_dml_rollback"));
    
    // Create our target table for the DML test
    await($client->query("CREATE TABLE test_dml_rollback (id INT PRIMARY KEY)"));

    // =========================================================================
    // TEST 1: Standard DML Transaction (Control)
    // =========================================================================
    echo "=== Running Test 1: Standard DML Transaction ===\n";
    try {
        await($client->transaction(function($tx) {
            echo "Inserting row into 'test_dml_rollback'...\n";
            await($tx->query("INSERT INTO test_dml_rollback (id) VALUES (999)"));
            
            echo "Forcing a transaction failure...\n";
            await($tx->query("SELECT * FROM non_existent_table_trigger_failure"));
        }));
    } catch (\Throwable $e) {
        echo "Transaction aborted successfully as expected: " . $e->getMessage() . "\n";
    }

    // Verify if the inserted row actually exists or was rolled back
    $row = await($client->fetchOne("SELECT * FROM test_dml_rollback WHERE id = 999"));
    if ($row === null) {
        echo "✅ RESULT: DML row was successfully ROLLED BACK. Row does not exist.\n\n";
    } else {
        echo "❌ RESULT: DML row was NOT rolled back.\n\n";
    }

    // =========================================================================
    // TEST 2: DDL (Schema) Transaction
    // =========================================================================
    echo "=== Running Test 2: DDL (Schema) Transaction ===\n";
    try {
        await($client->transaction(function($tx) {
            echo "Creating table 'test_ddl_rollback'...\n";
            await($tx->query("CREATE TABLE test_ddl_rollback (id INT PRIMARY KEY)"));
            
            echo "Forcing a transaction failure...\n";
            await($tx->query("SELECT * FROM non_existent_table_trigger_failure"));
        }));
    } catch (\Throwable $e) {
        echo "Transaction aborted successfully as expected: " . $e->getMessage() . "\n";
    }

    // Verify if the 'test_ddl_rollback' table was rolled back or if it still exists
    try {
        await($client->query("SELECT * FROM test_ddl_rollback"));
        echo "❌ RESULT (Expected MySQL Behavior): The table 'test_ddl_rollback' STILL EXISTS!\n";
        echo "This proves MySQL implicitly committed the CREATE TABLE and ignored your transaction rollback.\n\n";
    } catch (\Throwable $e) {
        if (str_contains(strtolower($e->getMessage()), "doesn't exist")) {
            echo "✅ RESULT: The table 'test_ddl_rollback' was successfully rolled back.\n\n";
        } else {
            echo "Error during verification: " . $e->getMessage() . "\n\n";
        }
    }

    // Cleanup
    await($client->query("DROP TABLE IF EXISTS test_ddl_rollback"));
    await($client->query("DROP TABLE IF EXISTS test_dml_rollback"));
    $client->close();
    
})->catch(function($e) {
    echo "Execution failed: " . $e->getMessage() . "\n";
});