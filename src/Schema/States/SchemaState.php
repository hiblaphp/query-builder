<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

use Hibla\QueryBuilder\Exceptions\SchemaMigrationException;
use Rcalicdan\ConfigLoader\Config;

abstract class SchemaState
{
    /**
     * Dump the database schema and migrations table to a file.
     *
     * @param array<string, mixed> $config
     */
    abstract public function dump(array $config, string $path, string $migrationsTable): void;

    /**
     * Load the database schema from a file.
     *
     * @param array<string, mixed> $config
     */
    abstract public function load(array $config, string $path): void;

    /**
     * Build the correct SchemaState driver based on the connection.
     *
     * @throws SchemaMigrationException
     */
    public static function make(?string $connectionName = null): self
    {
        $dbConfig = Config::loadFromRoot('hibla-database');
        $connectionName ??= $dbConfig['default'] ?? 'mysql';

        $config = $dbConfig['connections'][$connectionName] ?? [];
        $driver = strtolower($config['driver'] ?? 'mysql');

        return match ($driver) {
            'mysql', 'mysqli' => new MySQLSchemaState(),
            'pgsql', 'pgsql_native' => new PostgresSchemaState(),
            'sqlite' => new SQLiteSchemaState(),
            default => throw new SchemaMigrationException("Schema dumping is not supported for driver: {$driver}"),
        };
    }

    /**
     * Safely execute a command and stream its output to a file (Cross-Platform).
     *
     * @param list<string> $command
     * @param array<string, string> $env
     */
    protected function executeCommandAndWriteToFile(array $command, string $filePath, bool $append = false, array $env = []): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $processEnv = array_merge($_SERVER, $_ENV, $env);

        $process = @proc_open($command, $descriptors, $pipes, null, $processEnv);

        if (!is_resource($process)) {
            $cmdName = $command[0];
            throw new SchemaMigrationException(
                "Executable '{$cmdName}' could not be found.\n\n" .
                "Please check your System PATH and ensure '{$cmdName}' is installed and added to your System PATH.\n"
            );
        }

        fclose($pipes[0]);

        $outputFile = fopen($filePath, $append ? 'ab' : 'wb');
        if ($outputFile === false) {
            throw new SchemaMigrationException("Failed to open file for writing: {$filePath}");
        }

        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                fwrite($outputFile, $chunk);
            }
        }
        fclose($outputFile);

        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $cmdName = $command[0];
            throw new SchemaMigrationException("Command '{$cmdName}' failed (Exit {$exitCode}). Error: " . trim((string)$errorOutput));
        }
    }

    /**
     * Safely execute a command by streaming a file into its input (Cross-Platform).
     *
     * @param list<string> $command
     * @param array<string, string> $env
     */
    protected function executeCommandFromFile(array $command, string $filePath, array $env = []): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'], 
        ];

        $processEnv = array_merge($_SERVER, $_ENV, $env);
        $process = @proc_open($command, $descriptors, $pipes, null, $processEnv);

        if (!is_resource($process)) {
            $cmdName = $command[0];
            throw new SchemaMigrationException(
                "Executable '{$cmdName}' could not be found.\n\n" .
                "Please check your System PATH and ensure '{$cmdName}' is installed and added to your System PATH.\n"
            );
        }

        $inputFile = fopen($filePath, 'rb');

        if ($inputFile !== false) {
            while (!feof($inputFile)) {
                $chunk = fread($inputFile, 8192);
                if ($chunk !== false && $chunk !== '') {
                    fwrite($pipes[0], $chunk);
                }
            }
            fclose($inputFile);
        }

        fclose($pipes[0]);

        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $cmdName = $command[0];
            throw new SchemaMigrationException("Command '{$cmdName}' failed to load schema (Exit {$exitCode}). Error: " . trim((string)$errorOutput));
        }
    }
}
