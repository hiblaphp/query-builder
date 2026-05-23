<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Hibla\QueryBuilder\Console\Traits\InitializeDatabase;
use Hibla\QueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\QueryBuilder\Console\Traits\ValidateConnection;
use Hibla\QueryBuilder\Schema\MigrationRepository;
use InvalidArgumentException;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Hibla\await;

class MigrateRollbackCommand extends Command
{
    use LoadsSchemaConfiguration;
    use InitializeDatabase;
    use ValidateConnection;

    private SymfonyStyle $io;

    private OutputInterface $output;

    private ?string $projectRoot = null;

    private MigrationRepository $repository;

    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:rollback')
            ->setDescription('Rollback the last database migration')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to rollback', 1)
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Only rollback migrations from this path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Rollback Migrations');

        $this->setConnectionFromInput($input);

        try {
            $this->validateConnection($this->connection);
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        try {
            $this->initializeDatabase();
            $this->repository = new MigrationRepository($this->getMigrationsTable($this->connection), $this->connection);

            $step = $this->getStepFromInput($input);
            $path = $this->getPathFromInput($input);

            $rolledBack = $this->performRollback($step, $path);

            if (! $rolledBack) {
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);

            return Command::FAILURE;
        }
    }

    private function setConnectionFromInput(InputInterface $input): void
    {
        $connectionOption = $input->getOption('connection');
        $this->connection = (\is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }
    }

    private function getStepFromInput(InputInterface $input): int
    {
        $stepOption = $input->getOption('step');

        return is_numeric($stepOption) ? (int) $stepOption : 1;
    }

    private function getPathFromInput(InputInterface $input): ?string
    {
        $pathOption = $input->getOption('path');

        return \is_string($pathOption) && $pathOption !== '' ? $pathOption : null;
    }

    private function initializeProjectRoot(): bool
    {
        $this->projectRoot = Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return false;
        }

        return true;
    }

    private function performRollback(int $step, ?string $path): bool
    {
        /** @var list<array<string, mixed>> $ranMigrations */
        $ranMigrations = await($this->repository->getRan());

        if (\count($ranMigrations) === 0) {
            $this->io->info('Nothing to rollback.');

            return true;
        }

        $ranMigrations = $this->filterMigrationsByPath($ranMigrations, $path);

        if ($ranMigrations === null) {
            return true;
        }

        $ranMigrations = $this->limitMigrationsByStep($ranMigrations, $step);

        return $this->rollbackMigrations($ranMigrations);
    }

    /**
     * @param list<array<string, mixed>> $ranMigrations
     *
     * @return list<array<string, mixed>>|null
     */
    private function filterMigrationsByPath(array $ranMigrations, ?string $path): ?array
    {
        if ($path === null) {
            return $ranMigrations;
        }

        $normalizedPath = trim($path, '/') . '/';
        $filtered = array_filter($ranMigrations, function ($migration) use ($normalizedPath) {
            $migrationPath = $migration['migration'] ?? '';

            return \is_string($migrationPath) && str_starts_with($migrationPath, $normalizedPath);
        });

        if (\count($filtered) === 0) {
            $this->io->info("No migrations to rollback in path: {$path}");

            return null;
        }

        $this->io->note("Rolling back migrations from path: {$path}");

        return array_values($filtered);
    }

    /**
     * @param list<array<string, mixed>> $ranMigrations
     *
     * @return list<array<string, mixed>>
     */
    private function limitMigrationsByStep(array $ranMigrations, int $step): array
    {
        if ($step > 0) {
            return \array_slice($ranMigrations, 0, $step);
        }

        return $ranMigrations;
    }

    /**
     * @param list<array<string, mixed>> $ranMigrations
     */
    private function rollbackMigrations(array $ranMigrations): bool
    {
        $this->io->section('Rolling back migrations');

        foreach ($ranMigrations as $migrationData) {
            if (! $this->rollbackMigration($migrationData)) {
                return false;
            }
        }

        $this->io->success('Rollback completed successfully!');

        return true;
    }

    /**
     * @param array<string, mixed> $migrationData
     */
    private function rollbackMigration(array $migrationData): bool
    {
        $relativePath = $this->extractRelativePath($migrationData);

        if ($relativePath === null) {
            return false;
        }

        $file = $this->getFullMigrationPath($relativePath, $this->connection);

        if (! $this->validateMigrationFile($file, $relativePath)) {
            await($this->repository->delete($relativePath));
            $this->io->warning("Migration file not found but removed from repository: {$relativePath}");

            return true;
        }

        return $this->executeMigrationRollback($file, $relativePath);
    }

    /**
     * @param array<string, mixed> $migrationData
     */
    private function extractRelativePath(array $migrationData): ?string
    {
        $relativePath = $migrationData['migration'] ?? null;

        if (! \is_string($relativePath)) {
            $this->io->warning('Skipping invalid migration record.');

            return null;
        }

        return $relativePath;
    }

    private function executeMigrationRollback(string $file, string $relativePath): bool
    {
        try {
            $migration = $this->loadMigrationFile($file, $relativePath);

            if ($migration === null) {
                return false;
            }

            if (! $this->validateMigrationClass($migration, $relativePath)) {
                return false;
            }

            $migrationConnection = $this->determineMigrationConnection($migration);

            $this->displayRollbackProgress($relativePath, $migrationConnection);

            $this->executeDownMethod($migration);

            await($this->repository->delete($relativePath));

            $this->io->writeln(' <info>✓</info>');

            return true;
        } catch (\Throwable $e) {
            $this->handleMigrationError($e, $relativePath);

            return false;
        }
    }

    private function loadMigrationFile(string $file, string $relativePath): ?object
    {
        $migration = require $file;

        if (! \is_object($migration)) {
            $this->io->error("Migration file {$relativePath} did not return an object.");

            return null;
        }

        return $migration;
    }

    private function determineMigrationConnection(object $migration): ?string
    {
        $migrationConnection = $this->connection;

        if (method_exists($migration, 'getConnection')) {
            $declaredConnection = $migration->getConnection();
            if (\is_string($declaredConnection)) {
                $migrationConnection = $declaredConnection;
            }
        }

        return $migrationConnection;
    }

    private function displayRollbackProgress(string $relativePath, ?string $migrationConnection): void
    {
        $this->io->write("Rolling back: {$relativePath}");

        if ($migrationConnection !== null && $migrationConnection !== $this->connection) {
            $this->io->write(" <comment>[{$migrationConnection}]</comment>");
        }

        $this->io->write('...');
    }

    private function executeDownMethod(object $migration): void
    {
        $migration->down();
    }

    private function handleMigrationError(\Throwable $e, string $relativePath): void
    {
        $this->io->newLine();
        $this->io->error("Failed to rollback migration {$relativePath}: " . $e->getMessage());

        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function validateMigrationFile(string $file, string $migrationName): bool
    {
        return file_exists($file);
    }

    private function validateMigrationClass(object $migration, string $migrationName): bool
    {
        if (! method_exists($migration, 'down')) {
            $this->io->error("Migration {$migrationName} does not have a down() method");

            return false;
        }

        return true;
    }

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Rollback failed: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }
}
