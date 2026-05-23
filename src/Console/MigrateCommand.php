<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Hibla\QueryBuilder\Console\Traits\InitializeDatabase;
use Hibla\QueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\QueryBuilder\Console\Traits\ValidateConnection;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;
use Hibla\QueryBuilder\Schema\DatabaseManager;
use Hibla\QueryBuilder\Schema\MigrationRepository;
use Hibla\QueryBuilder\Schema\States\SchemaState;
use InvalidArgumentException;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Hibla\QueryBuilder\DB;

use function Hibla\await;

class MigrateCommand extends Command
{
    use LoadsSchemaConfiguration;
    use InitializeDatabase;
    use ValidateConnection;

    private SymfonyStyle $io;

    private OutputInterface $output;

    private ?string $projectRoot = null;

    private ?string $connection = null;

    /**
     * @var array<string, MigrationRepository>
     */
    private array $repositories = [];

    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('Run pending database migrations')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to run', 0)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run without prompts')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migrations files (relative to migrations directory)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeIo($input, $output);
        $this->io->title('Database Migrations');

        $this->setConnectionFromInput($input);

        try {
            $this->validateConnection($this->connection);
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->projectRoot = Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return Command::FAILURE;
        }

        try {
            return $this->runMigrations($input);
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

    private function runMigrations(InputInterface $input): int
    {
        $force = (bool) $input->getOption('force');

        $dbCheckResult = $this->ensureDatabaseExists($force);

        if ($dbCheckResult === false) {
            return Command::FAILURE;
        }

        if ($dbCheckResult === null) {
            $this->io->warning('Migration cancelled by user');

            return Command::SUCCESS;
        }

        $this->initializeDatabase();

        $this->loadSchemaStateIfNeeded();

        $this->io->writeln('Preparing migration repository...');

        $primaryRepository = $this->getRepository($this->connection);
        await($primaryRepository->createRepository());

        $step = $this->getStepOption($input);
        $path = $this->getPathOption($input);

        $migrationResult = $this->performMigration($step, $path);

        if ($migrationResult === false) {
            return Command::FAILURE;
        }

        if ($migrationResult === true) {
            $this->io->success('Migrations completed successfully!');
        }

        return Command::SUCCESS;
    }

    /**
     * Load the schema state file if it exists and the DB is empty.
     */
    private function loadSchemaStateIfNeeded(): void
    {
        $repository = $this->getRepository($this->connection);
        
        // If the repository exists, the database is already initialized. Skip loading schema.
        if (await($repository->repositoryExists()) > 0) {
            return;
        }

        $schemaConfig = $this->getSchemaConfig($this->connection);
        $connectionName = $this->connection ?? 'mysql';
        $schemaPath = $schemaConfig['schema_path'] . DIRECTORY_SEPARATOR . $connectionName . '-schema.sql';

        if (file_exists($schemaPath)) {
            $this->io->write("Loading schema state from <comment>{$schemaPath}</comment>... ");
            
            try {
                $state = SchemaState::make($this->connection);
                $dbConfig = $this->getDatabaseConfig($this->connection);

                $state->load($dbConfig, $schemaPath);
                $this->io->writeln("<info>✓</info>");
            } catch (\Throwable $e) {
                $this->io->newLine();
                $this->io->error("Failed to load schema: " . $e->getMessage());
                throw $e;
            }
        }
    }

    private function getStepOption(InputInterface $input): int
    {
        $stepOption = $input->getOption('step');

        return is_numeric($stepOption) ? (int) $stepOption : 0;
    }

    private function getPathOption(InputInterface $input): ?string
    {
        $pathOption = $input->getOption('path');

        return \is_string($pathOption) && $pathOption !== '' ? $pathOption : null;
    }

    /**
     * Get or create a migration repository for a specific connection.
     */
    private function getRepository(?string $connection): MigrationRepository
    {
        $key = $connection ?? 'default';

        if (! isset($this->repositories[$key])) {
            $this->repositories[$key] = new MigrationRepository(
                $this->getMigrationsTable($connection),
                $connection
            );
        }

        return $this->repositories[$key];
    }

    /**
     * @return bool|null true = success, false = error, null = user declined
     */
    private function ensureDatabaseExists(bool $force): ?bool
    {
        try {
            $dbManager = new DatabaseManager($this->connection);

            $exists = await($dbManager->databaseExists());

            if (! $exists) {
                return $this->handleMissingDatabase($force);
            }

            return true;
        } catch (\Throwable $e) {
            return $this->handleDatabaseConnectionError($e, $force);
        }
    }

    private function handleMissingDatabase(bool $force): ?bool
    {
        $dbName = $this->getDatabaseName();
        $this->io->warning("Database '{$dbName}' does not exist!");

        if (! $force && ! $this->confirmDatabaseCreation($dbName)) {
            return null;
        }

        return $this->createDatabase();
    }

    private function confirmDatabaseCreation(string $dbName): bool
    {
        return $this->io->confirm(
            "Do you want to create the database '{$dbName}'?",
            false
        );
    }

    private function createDatabase(): bool
    {
        try {
            $this->io->writeln('<comment>Creating database...</comment>');
            $dbManager = new DatabaseManager($this->connection);

            await($dbManager->createDatabaseIfNotExists());

            $this->io->writeln('<info>✓ Database created successfully!</info>');
            $this->io->newLine();

            return true;
        } catch (\Throwable $createError) {
            $this->displayDatabaseCreationError($createError);

            return false;
        }
    }

    private function handleDatabaseConnectionError(\Throwable $e, bool $force): ?bool
    {
        if ($this->isDatabaseNotExistError($e)) {
            return $this->handleMissingDatabase($force);
        }

        $this->displayDatabaseConnectionError($e);

        return false;
    }

    private function displayDatabaseConnectionError(\Throwable $e): void
    {
        $this->io->error('Database connection failed: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function displayDatabaseCreationError(\Throwable $e): void
    {
        $this->io->error('Failed to create database: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function getDatabaseName(): string
    {
        try {
            $dbConfig = Config::loadFromRoot('hibla-database');

            if (! \is_array($dbConfig)) {
                return 'unknown';
            }

            /** @var array<string, mixed> $typedConfig */
            $typedConfig = $dbConfig;

            $connectionName = $this->getConnectionName($typedConfig);
            $connections = $this->getConnections($typedConfig);
            $config = $connections[$connectionName] ?? [];

            if (! \is_array($config)) {
                return 'unknown';
            }

            $database = $config['database'] ?? 'unknown';

            return \is_string($database) ? $database : 'unknown';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * Get the database configuration for the specified connection.
     *
     * @param string|null $connection
     * @return array<string, mixed>
     */
    private function getDatabaseConfig(?string $connection = null): array
    {
        try {
            $dbConfig = Config::loadFromRoot('hibla-database');
            
            if (! \is_array($dbConfig)) {
                return [];
            }
            
            $connectionName = $connection ?? ($dbConfig['default'] ?? 'mysql');
            $connections = $dbConfig['connections'] ?? [];
            
            if (! \is_array($connections)) {
                return [];
            }
            
            $config = $connections[$connectionName] ?? [];
            return \is_array($config) ? $config : [];
        } catch (\Throwable $e) {
            return [];
        }
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

    private function isDatabaseNotExistError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'does not exist') ||
            str_contains($message, 'unknown database') ||
            (str_contains($message, 'database') && str_contains($message, 'not found')) ||
            str_contains($message, 'cannot connect to database');
    }

    private function performMigration(int $step, ?string $path): ?bool
    {
        $pendingMigrations = $this->getPendingMigrations($path);

        if (\count($pendingMigrations) === 0) {
            $this->io->success('Nothing to migrate');

            return null;
        }

        $migrationsToRun = $this->limitMigrationsByStep($pendingMigrations, $step);

        $this->displayMigrationHeader($path);

        return $this->executeMigrationsByConnection($migrationsToRun);
    }

    /**
     * @param list<string> $migrations
     *
     * @return list<string>
     */
    private function limitMigrationsByStep(array $migrations, int $step): array
    {
        if ($step > 0) {
            return \array_slice($migrations, 0, $step);
        }

        return $migrations;
    }

    private function displayMigrationHeader(?string $path): void
    {
        $this->io->section('Running migrations');

        if ($path !== null) {
            $this->io->note("Running migrations from path: {$path}");
        }
    }

    /**
     * @param list<string> $migrations
     */
    private function executeMigrationsByConnection(array $migrations): bool
    {
        $migrationsByConnection = $this->groupMigrationsByConnection($migrations);

        foreach ($migrationsByConnection as $conn => $files) {
            if (! $this->executeMigrationsForConnection($conn, $files)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $files
     */
    private function executeMigrationsForConnection(string $conn, array $files): bool
    {
        $connection = $conn === 'default' ? null : $conn;
        $repository = $this->getRepository($connection);

        await($repository->createRepository());

        $batchNumber = $this->getNextBatchNumber($repository);

        foreach ($files as $file) {
            if (! $this->runMigration($file, $batchNumber, $connection)) {
                return false;
            }
        }

        return true;
    }

    private function getNextBatchNumber(MigrationRepository $repository): int
    {
        $batchNumber = await($repository->getNextBatchNumber());

        return (\is_int($batchNumber) ? $batchNumber : 0) + 1;
    }

    /**
     * @return list<string>
     */
    private function getPendingMigrations(?string $path): array
    {
        $files = $this->getMigrationFiles($path);

        if (\count($files) === 0) {
            return [];
        }

        return $this->filterPendingMigrations($files);
    }

    /**
     * @return list<string>
     */
    private function getMigrationFiles(?string $path): array
    {
        if ($path !== null) {
            $pattern = rtrim($path, '/') . '/*.php';

            return $this->getFilteredMigrationFiles($pattern, null);
        }

        return $this->getAllMigrationFiles(null);
    }

    /**
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function filterPendingMigrations(array $files): array
    {
        $pending = [];

        foreach ($files as $file) {
            if ($this->shouldIncludeMigration($file)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    private function shouldIncludeMigration(string $file): bool
    {
        $migrationConnection = $this->getMigrationConnectionFromFile($file);

        if (! $this->isConnectionMatching($migrationConnection)) {
            return false;
        }

        return ! $this->isMigrationAlreadyRan($file, $migrationConnection);
    }

    private function isConnectionMatching(?string $migrationConnection): bool
    {
        if ($this->connection !== null) {
            return $migrationConnection === $this->connection;
        }

        return $migrationConnection === null;
    }

    private function isMigrationAlreadyRan(string $file, ?string $migrationConnection): bool
    {
        $relativePath = $this->getRelativeMigrationPath($file, $migrationConnection);
        $repository = $this->getRepository($migrationConnection);

        await($repository->createRepository());

        $ranMigrations = await($repository->getRan());
        $ranMigrationPaths = array_column($ranMigrations, 'migration');

        return \in_array($relativePath, $ranMigrationPaths, true);
    }

    private function getMigrationConnectionFromFile(string $file): ?string
    {
        try {
            if (! file_exists($file)) {
                return null;
            }

            $migration = require $file;

            if (! \is_object($migration)) {
                return null;
            }

            return $this->extractConnectionFromMigration($migration);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractConnectionFromMigration(object $migration): ?string
    {
        if (! method_exists($migration, 'getConnection')) {
            return $this->extractConnectionFromProperty($migration);
        }

        $connection = $migration->getConnection();

        return \is_string($connection) ? $connection : null;
    }

    private function extractConnectionFromProperty(object $migration): ?string
    {
        try {
            $reflection = new \ReflectionObject($migration);

            if (! $reflection->hasProperty('connection')) {
                return null;
            }

            $property = $reflection->getProperty('connection');
            $property->setAccessible(true);

            $value = $property->getValue($migration);

            return \is_string($value) ? $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param list<string> $files
     *
     * @return array<string, list<string>>
     */
    private function groupMigrationsByConnection(array $files): array
    {
        $grouped = [];

        foreach ($files as $file) {
            $connection = $this->getMigrationConnectionFromFile($file);
            $key = $connection ?? 'default';

            if (! isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][] = $file;
        }

        return $grouped;
    }

    private function runMigration(string $file, int $batchNumber, ?string $migrationConnection): bool
    {
        $relativePath = $this->getRelativeMigrationPath($file, $this->connection);
        $displayName = $relativePath;

        try {
            if (! file_exists($file)) {
                $this->io->error("Migration file not found: {$displayName}");

                return false;
            }

            $migration = $this->loadMigrationFile($file, $displayName);

            if ($migration === null) {
                return false;
            }

            $this->displayMigrationProgress($displayName, $migrationConnection);

            $useTransaction = method_exists($migration, 'shouldRunWithinTransaction') && $migration->shouldRunWithinTransaction();

            if ($useTransaction) {
                await(
                    DB::connection($migrationConnection)->transaction(function ($tx) use ($migration, $relativePath, $batchNumber, $migrationConnection) {
                        if (method_exists($migration, 'setTransaction')) {
                            $migration->setTransaction($tx);
                        }
                        $this->executeMigration($migration);
                        $this->logMigration($relativePath, $batchNumber, $migrationConnection, $tx);
                    })
                );
            } else {
                $this->executeMigration($migration);
                $this->logMigration($relativePath, $batchNumber, $migrationConnection);
            }

            $this->io->writeln(' <info>✓</info>');

            return true;
        } catch (\Throwable $e) {
            $this->displayMigrationError($displayName, $e);

            return false;
        }
    }

    private function loadMigrationFile(string $file, string $displayName): ?object
    {
        $migration = require $file;

        if (! \is_object($migration) || ! $this->validateMigrationClass($migration, $displayName)) {
            return null;
        }

        return $migration;
    }

    private function displayMigrationProgress(string $displayName, ?string $migrationConnection): void
    {
        $this->io->write("Migrating: {$displayName}");

        if ($migrationConnection !== null && $migrationConnection !== $this->connection) {
            $this->io->write(" <comment>[{$migrationConnection}]</comment>");
        }

        $this->io->write('...');
    }

    private function executeMigration(object $migration): void
    {
        $migration->up();
    }

    private function logMigration(string $relativePath, int $batchNumber, ?string $migrationConnection, ?DatabaseTransactionInterface $tx = null): void
    {
        $repository = $this->getRepository($migrationConnection);
        await($repository->log($relativePath, $batchNumber, $tx));
    }

    private function displayMigrationError(string $displayName, \Throwable $e): void
    {
        $this->io->newLine();
        $this->io->error("Failed to run migration {$displayName}: " . $e->getMessage());

        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function validateMigrationClass(object $migration, string $migrationName): bool
    {
        if (! method_exists($migration, 'up')) {
            $this->io->error("Migration {$migrationName} does not have an up() method");

            return false;
        }

        return true;
    }

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Migration failed: ' . $e->getMessage());

        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }
}