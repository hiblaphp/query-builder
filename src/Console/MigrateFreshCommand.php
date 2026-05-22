<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Hibla\QueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\QueryBuilder\Console\Traits\ValidateConnection;
use Hibla\QueryBuilder\DB;
use InvalidArgumentException;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Hibla\await;

class MigrateFreshCommand extends Command
{
    use LoadsSchemaConfiguration;
    use ValidateConnection;

    private SymfonyStyle $io;

    private OutputInterface $output;

    private ?string $projectRoot = null;

    private string $driver;

    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:fresh')
            ->setDescription('Drop all tables and re-run all migrations')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migrations files')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeIo($input, $output);
        $this->io->title('Fresh Migration');

        $this->setConnectionFromInput($input);

        try {
            $this->validateConnection($this->connection);
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (! $this->shouldProceed($input)) {
            $this->io->warning('Fresh migration cancelled');

            return Command::SUCCESS;
        }

        $this->projectRoot = Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return Command::FAILURE;
        }

        try {
            return $this->performFreshMigration($input);
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);

            return Command::FAILURE;
        }
    }

    private function initializeIo(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
    }

    private function setConnectionFromInput(InputInterface $input): void
    {
        $connectionOption = $input->getOption('connection');
        $this->connection = (\is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }
    }

    private function shouldProceed(InputInterface $input): bool
    {
        $force = (bool) $input->getOption('force');

        if ($force) {
            return true;
        }

        return $this->confirmFresh();
    }

    private function performFreshMigration(InputInterface $input): int
    {
        $this->driver = $this->detectDriver();
        $this->initializeDatabase();

        if (! $this->dropAllTablesWithFeedback()) {
            return Command::FAILURE;
        }

        $path = $this->getPathOption($input);

        if (! $this->runMigrationsWithFeedback($path)) {
            return Command::FAILURE;
        }

        $this->io->success('Database refreshed successfully!');

        return Command::SUCCESS;
    }

    private function getPathOption(InputInterface $input): ?string
    {
        $pathOption = $input->getOption('path');

        return \is_string($pathOption) && $pathOption !== '' ? $pathOption : null;
    }

    private function dropAllTablesWithFeedback(): bool
    {
        $this->io->section('Dropping all tables...');

        if (! $this->dropAllTables()) {
            return false;
        }

        $this->io->success('All tables dropped successfully!');

        return true;
    }

    private function runMigrationsWithFeedback(?string $path): bool
    {
        $this->io->section('Running migrations...');

        if (! $this->runMigrations($path)) {
            $this->io->error('Migration failed');

            return false;
        }

        return true;
    }

    private function confirmFresh(): bool
    {
        $connectionName = $this->getConnectionDisplayName();

        $this->io->warning([
            "This will DROP ALL TABLES for connection '{$connectionName}'!",
            'All data will be permanently lost.',
        ]);

        return $this->io->confirm('Are you absolutely sure you want to continue?', false);
    }

    private function getConnectionDisplayName(): string
    {
        return $this->connection ?? $this->getDefaultConnection();
    }

    private function dropAllTables(): bool
    {
        try {
            $migratedTables = $this->getMigratedTables();

            if ($this->noTablesToDrop($migratedTables)) {
                return true;
            }

            $this->displayTablesCount($migratedTables);

            $this->disableForeignKeyChecks();

            $this->dropMigratedTables($migratedTables);
            $this->dropMigrationsTable();

            $this->enableForeignKeyChecks();

            return true;
        } catch (\Throwable $e) {
            $this->displayDropTablesError($e);

            return false;
        }
    }

    /**
     * @param list<string> $tables
     */
    private function noTablesToDrop(array $tables): bool
    {
        if (\count($tables) === 0) {
            $connectionName = $this->getConnectionDisplayName();
            $this->io->note("No migrated tables found for connection '{$connectionName}'");

            return true;
        }

        return false;
    }

    /**
     * @param list<string> $tables
     */
    private function displayTablesCount(array $tables): void
    {
        $connectionName = $this->getConnectionDisplayName();
        $this->io->writeln(\sprintf(
            'Found %d migrated table(s) to drop for connection: %s',
            \count($tables),
            $connectionName
        ));
    }

    /**
     * @param list<string> $tables
     */
    private function dropMigratedTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->dropTableWithFeedback($table);
        }
    }

    private function dropMigrationsTable(): void
    {
        $migrationsTable = $this->getMigrationsTable($this->connection);
        $this->io->write("Dropping migrations table: {$migrationsTable}...");
        $this->dropTable($migrationsTable);
        $this->io->writeln(' <info>✓</info>');
    }

    private function dropTableWithFeedback(string $table): void
    {
        $this->io->write("Dropping table: {$table}...");
        $this->dropTable($table);
        $this->io->writeln(' <info>✓</info>');
    }

    private function displayDropTablesError(\Throwable $e): void
    {
        $this->io->error('Failed to drop tables: ' . $e->getMessage());

        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    /**
     * @return list<string>
     */
    private function getMigratedTables(): array
    {
        $migrationFiles = $this->getAllMigrationFiles($this->connection);
        $targetConnection = $this->getTargetConnection();

        $tables = $this->extractTablesFromMigrations($migrationFiles, $targetConnection);

        sort($tables);

        return $tables;
    }

    private function getTargetConnection(): string
    {
        $defaultConnection = $this->getDefaultConnection();

        return $this->connection ?? $defaultConnection;
    }

    /**
     * @param list<string> $migrationFiles
     *
     * @return list<string>
     */
    private function extractTablesFromMigrations(array $migrationFiles, string $targetConnection): array
    {
        $tables = [];
        $defaultConnection = $this->getDefaultConnection();

        foreach ($migrationFiles as $file) {
            $tablesInFile = $this->extractTablesFromMigrationFile($file, $targetConnection, $defaultConnection);
            $tables = array_merge($tables, $tablesInFile);
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return list<string>
     */
    private function extractTablesFromMigrationFile(
        string $file,
        string $targetConnection,
        string $defaultConnection
    ): array {
        $content = file_get_contents($file);

        if ($content === false) {
            return [];
        }

        if (! $this->isMigrationForTargetConnection($content, $targetConnection, $defaultConnection)) {
            return [];
        }

        return $this->parseTableNamesFromContent($content);
    }

    private function isMigrationForTargetConnection(
        string $content,
        string $targetConnection,
        string $defaultConnection
    ): bool {
        $migrationConnection = $this->extractMigrationConnection($content);

        if ($migrationConnection === null) {
            $migrationConnection = $defaultConnection;
        }

        return $migrationConnection === $targetConnection;
    }

    /**
     * @return list<string>
     */
    private function parseTableNamesFromContent(string $content): array
    {
        $tables = [];

        $matchResult = preg_match_all('/->create\([\'"]([^\'"]+)[\'"]\s*,/i', $content, $matches);

        if ($matchResult !== false && $matchResult > 0) {
            return $matches[1];
        }

        return $tables;
    }

    /**
     * Extract the connection property from a migration file content
     */
    private function extractMigrationConnection(string $content): ?string
    {
        $matchResult = preg_match('/protected\s+\??string\s+\$connection\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);

        if ($matchResult !== false && $matchResult > 0) {
            return $matches[1];
        }

        $matchResult = preg_match('/protected\s+\$connection\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);

        if ($matchResult !== false && $matchResult > 0) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get the default database connection name
     */
    private function getDefaultConnection(): string
    {
        try {
            $dbConfig = $this->getDatabaseConfig();

            if ($dbConfig === null) {
                return 'mysql';
            }

            $default = $dbConfig['default'] ?? 'mysql';

            return \is_string($default) ? $default : 'mysql';
        } catch (\Throwable $e) {
            return 'mysql';
        }
    }

    private function dropTable(string $table): void
    {
        $sql = $this->getDropTableSql($table);
        $promise = DB::connection($this->connection)->raw($sql);
        await($promise);
    }

    private function getDropTableSql(string $table): string
    {
        return match ($this->driver) {
            'pgsql' => "DROP TABLE IF EXISTS \"{$table}\" CASCADE",
            'mysql' => "DROP TABLE IF EXISTS `{$table}`",
            'sqlite' => "DROP TABLE IF EXISTS `{$table}`",
            default => "DROP TABLE IF EXISTS `{$table}`",
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getDatabaseConfig(): ?array
    {
        $dbConfig = Config::loadFromRoot('hibla-database');

        if (! \is_array($dbConfig)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $dbConfig;
    }

    /**
     * @param array<string, mixed> $dbConfig
     */
    private function getConnectionName(array $dbConfig): string
    {
        $connectionName = $this->connection ?? ($dbConfig['default'] ?? 'mysql');

        return \is_string($connectionName) ? $connectionName : 'mysql';
    }

    /**
     * @param array<string, mixed> $dbConfig
     *
     * @return array<string, mixed>
     */
    private function getConnections(array $dbConfig): array
    {
        $connections = $dbConfig['connections'] ?? [];

        if (! \is_array($connections)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $connections;
    }

    /**
     * @param array<string, mixed> $connections
     *
     * @return array<string, mixed>|null
     */
    private function getConnectionConfig(array $connections, string $connectionName): ?array
    {
        $connectionConfig = $connections[$connectionName] ?? [];

        if (! \is_array($connectionConfig)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $connectionConfig;
    }

    private function disableForeignKeyChecks(): void
    {
        $sql = $this->getDisableForeignKeyChecksSql();

        if ($sql !== null) {
            $this->executeForeignKeyChecksSql($sql, 'disable');
        }
    }

    private function enableForeignKeyChecks(): void
    {
        $sql = $this->getEnableForeignKeyChecksSql();

        if ($sql !== null) {
            $this->executeForeignKeyChecksSql($sql, 'enable');
        }
    }

    private function getDisableForeignKeyChecksSql(): ?string
    {
        return match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS=0',
            'pgsql' => 'SET CONSTRAINTS ALL DEFERRED',
            'sqlite' => 'PRAGMA foreign_keys = OFF',
            default => null,
        };
    }

    private function getEnableForeignKeyChecksSql(): ?string
    {
        return match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS=1',
            'pgsql' => 'SET CONSTRAINTS ALL IMMEDIATE',
            'sqlite' => 'PRAGMA foreign_keys = ON',
            default => null,
        };
    }

    private function executeForeignKeyChecksSql(string $sql, string $action): void
    {
        try {
            $promise = DB::connection($this->connection)->raw($sql);
            await($promise);
        } catch (\Throwable $e) {
            $this->logVerboseError("Warning: Could not {$action} foreign key checks: " . $e->getMessage());
        }
    }

    private function logVerboseError(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->io->writeln($message);
        }
    }

    private function runMigrations(?string $path): bool
    {
        $application = $this->getApplication();

        if ($application === null) {
            $this->io->error('Could not find application instance.');

            return false;
        }

        $command = $application->find('migrate');
        $arguments = $this->buildMigrationArguments($path);
        $input = new ArrayInput($arguments);

        return $command->run($input, $this->output) === Command::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function buildMigrationArguments(?string $path): array
    {
        $arguments = [];

        if ($this->connection !== null) {
            $arguments['--connection'] = $this->connection;
        }

        if ($path !== null) {
            $arguments['--path'] = $path;
        }

        return $arguments;
    }

    private function detectDriver(): string
    {
        try {
            $dbConfig = $this->getDatabaseConfig();

            if ($dbConfig === null) {
                return 'mysql';
            }

            $connectionName = $this->getConnectionName($dbConfig);
            $connections = $this->getConnections($dbConfig);
            $connectionConfig = $this->getConnectionConfig($connections, $connectionName);

            if ($connectionConfig === null) {
                return 'mysql';
            }

            $driver = $connectionConfig['driver'] ?? 'mysql';

            return \is_string($driver) ? strtolower($driver) : 'mysql';
        } catch (\Throwable $e) {
            return 'mysql';
        }
    }

    private function initializeDatabase(): void
    {
        try {
            $testQuery = 'SELECT 1';
            await(DB::connection($this->connection)->raw($testQuery));
        } catch (\Throwable $e) {
            $this->logVerboseError('Database initialization: ' . $e->getMessage());
        }
    }

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Fresh migration failed: ' . $e->getMessage());

        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }
}
