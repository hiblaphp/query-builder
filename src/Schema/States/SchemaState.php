<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Exceptions\SchemaMigrationException;
use Rcalicdan\ConfigLoader\Config;

use function Hibla\await;

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
     * Resolves the active database driver from configuration and returns the
     * appropriate SchemaState implementation (MySQL, PostgreSQL, or SQLite).
     *
     * @throws SchemaMigrationException If the driver is unsupported or schema dumping is unavailable.
     */
    public static function make(?string $connectionName = null): self
    {
        $rawConfig = Config::loadFromRoot('hibla-database');

        /** @var array<string, mixed> $dbConfig */
        $dbConfig = \is_array($rawConfig) ? $rawConfig : [];

        $defaultConnection = isset($dbConfig['default']) && \is_string($dbConfig['default'])
            ? $dbConfig['default']
            : 'mysql';

        $connectionName ??= $defaultConnection;

        $connections = isset($dbConfig['connections']) && \is_array($dbConfig['connections'])
            ? $dbConfig['connections']
            : [];

        /** @var array<string, mixed> $config */
        $config = isset($connections[$connectionName]) && \is_array($connections[$connectionName])
            ? $connections[$connectionName]
            : [];

        $rawDriver = isset($config['driver']) && \is_string($config['driver'])
            ? $config['driver']
            : 'mysql';

        $driver = strtolower($rawDriver);

        return match ($driver) {
            'mysql', 'mysqli' => new MySQLSchemaState(),
            'pgsql', 'pgsql_native' => new PostgresSchemaState(),
            'sqlite' => new SQLiteSchemaState(),
            default => throw new SchemaMigrationException(
                "Schema dumping is not supported for driver: {$driver}"
            ),
        };
    }

    /**
     * Safely execute a command and stream its output to a file (cross-platform).
     *
     * Spawns the given command via {@see proc_open}, reads its stdout in chunks,
     * and writes the result to {@see $filePath}. Throws on a non-zero exit code
     * or if the executable cannot be found.
     *
     * @param list<string> $command The command and its arguments.
     * @param string $filePath Destination file path for the output.
     * @param bool $append When true the output is appended; otherwise the file is overwritten.
     * @param array<string, string> $env Additional environment variables to inject into the process.
     *
     * @throws SchemaMigrationException On spawn failure, file-open failure, or non-zero exit.
     */
    protected function executeCommandAndWriteToFile(
        array $command,
        string $filePath,
        bool $append = false,
        array $env = [],
    ): void {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        /** @var array<string, mixed> $processEnv */
        $processEnv = array_merge($_SERVER, $_ENV, $env);

        $process = @proc_open($command, $descriptors, $pipes, null, $processEnv);

        if (! \is_resource($process)) {
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

        while (! feof($pipes[1])) {
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

            throw new SchemaMigrationException(
                "Command '{$cmdName}' failed (Exit {$exitCode}). Error: " . trim((string) $errorOutput)
            );
        }
    }

    /**
     * Safely execute a command by streaming a file into its standard input (cross-platform).
     *
     * Spawns the given command via {@see proc_open} and pipes the contents of
     * {@see $filePath} into the process stdin in chunks. Throws \RuntimeException
     * if the executable is missing, enabling seamless fallback to pure PHP execution.
     *
     * @param list<string> $command The command and its arguments.
     * @param string $filePath Source file whose contents are piped to the process.
     * @param array<string, string> $env Additional environment variables to inject into the process.
     *
     * @throws \RuntimeException If the executable is missing from the system PATH.
     * @throws SchemaMigrationException On non-zero exit from an executed command.
     */
    protected function executeCommandFromFile(
        array $command,
        string $filePath,
        array $env = [],
    ): void {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        /** @var array<string, mixed> $processEnv */
        $processEnv = array_merge($_SERVER, $_ENV, $env);

        $process = @proc_open($command, $descriptors, $pipes, null, $processEnv);

        if (! \is_resource($process)) {
            $cmdName = $command[0];

            throw new \RuntimeException("Executable '{$cmdName}' could not be found.");
        }

        $inputFile = fopen($filePath, 'rb');

        if ($inputFile !== false) {
            while (! feof($inputFile)) {
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
            $errLower = strtolower(trim((string) $errorOutput));

            // Catch Windows specific 'not recognized' errors to trigger the PHP Fallback
            if (
                str_contains($errLower, 'not recognized') ||
                str_contains($errLower, 'not found') ||
                str_contains($errLower, 'no such file')
            ) {
                throw new \RuntimeException("Executable '{$cmdName}' could not be found.");
            }

            throw new SchemaMigrationException(
                "Command '{$cmdName}' failed to load schema (Exit {$exitCode}). Error: " . trim((string) $errorOutput)
            );
        }
    }

    /**
     * Fallback to pure PHP execution if the CLI tools are missing.
     *
     * @param array<string, mixed> $config Database connection configuration.
     * @param string $path Path to the SQL dump file to load.
     *
     * @throws SchemaMigrationException
     */
    protected function loadViaPhpFallback(array $config, string $path): void
    {
        $sql = file_get_contents($path);

        if ($sql === false || trim($sql) === '') {
            return;
        }

        // Enable multi-statements so the database executes the entire dump file at once,
        // Note this option is ignored by other driver that dont have strict check for multiline statements
        $config['multi_statements'] = true;

        $client = DB::resolveClientFromConfig($config);

        try {
            await($client->execute($sql));
        } catch (\Throwable $e) {
            throw new SchemaMigrationException(
                'Pure PHP Fallback failed to load schema: ' . $e->getMessage()
            );
        } finally {
            $client->close();
        }
    }
}
