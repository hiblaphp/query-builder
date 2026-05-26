<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Hibla\QueryBuilder\Console\Traits\InitializeDatabase;
use Hibla\QueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\QueryBuilder\Console\Traits\ValidateConnection;
use Hibla\QueryBuilder\Exceptions\DatabaseConfigurationException;
use Hibla\QueryBuilder\Schema\MigrationRepository;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Hibla\await;

class MigrateStatusCommand extends Command
{
    use LoadsSchemaConfiguration;
    use InitializeDatabase;
    use ValidateConnection;

    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show the status of each migration')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Only show migrations from this path')
            ->addOption('pending', null, InputOption::VALUE_NONE, 'Only show pending migrations')
            ->addOption('ran', null, InputOption::VALUE_NONE, 'Only show completed migrations')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Show the status of migrations for all configured connections')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Migration Status');

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if ($input->getOption('all') === true) {
            return $this->handleAllConnections($input);
        }

        $this->setConnectionFromInput($input);

        try {
            $this->validateConnection($this->connection);
        } catch (DatabaseConfigurationException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $this->initializeDatabase();

            $path = $this->getPathFromInput($input);
            $pendingOnly = (bool) $input->getOption('pending');
            $ranOnly = (bool) $input->getOption('ran');

            $this->displayMigrationStatus($path, $pendingOnly, $ranOnly);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleError($e);

            return Command::FAILURE;
        }
    }

    private function handleAllConnections(InputInterface $input): int
    {
        $connections = $this->getAvailableConnections();

        if (\count($connections) === 0) {
            $this->io->warning('No database connections configured.');

            return Command::SUCCESS;
        }

        $path = $this->getPathFromInput($input);
        $pendingOnly = (bool) $input->getOption('pending');
        $ranOnly = (bool) $input->getOption('ran');

        foreach ($connections as $conn) {
            $this->io->section("Connection: {$conn}");
            $this->connection = $conn;

            try {
                $this->validateConnection($this->connection);
                $this->initializeDatabase();
                $this->displayMigrationStatus($path, $pendingOnly, $ranOnly);
            } catch (\Throwable $e) {
                $this->handleError($e);

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
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

    private function setConnectionFromInput(InputInterface $input): void
    {
        $connectionOption = $input->getOption('connection');
        $this->connection = (\is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }
    }

    private function getPathFromInput(InputInterface $input): ?string
    {
        $pathOption = $input->getOption('path');

        return \is_string($pathOption) && $pathOption !== '' ? $pathOption : null;
    }

    private function displayMigrationStatus(?string $path, bool $pendingOnly, bool $ranOnly): void
    {
        $localFiles = $this->loadLocalMigrationFiles($path);
        $dbMigrations = $this->loadRanMigrationsFromDatabase();

        if (\count($localFiles) === 0 && \count($dbMigrations) === 0) {
            $this->io->warning('No migrations found in files or database.');

            return;
        }

        $rows = $this->buildUnifiedStatusRows($localFiles, $dbMigrations, $pendingOnly, $ranOnly);

        if (! $this->displayRowsOrEmptyMessage($rows, $pendingOnly, $ranOnly)) {
            return;
        }

        $this->displayStatusTable($rows);
        $this->displaySummary($rows);
    }

    /**
     * @return list<string>
     */
    private function loadLocalMigrationFiles(?string $path): array
    {
        if ($path !== null) {
            $pattern = rtrim($path, '/') . '/*.php';
            $files = $this->getFilteredMigrationFiles($pattern, $this->connection);
        } else {
            $files = $this->getAllMigrationFiles($this->connection);
        }

        $connToMatch = $this->connection ?? 'default';

        return $this->filterMigrationsByConnection($files, $connToMatch);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRanMigrationsFromDatabase(): array
    {
        try {
            $repository = new MigrationRepository(
                $this->getMigrationsTable($this->connection),
                $this->connection
            );

            if (await($repository->repositoryExists()) === 0) {
                return [];
            }

            $ran = await($repository->getRan());

            return \is_array($ran) ? array_values($ran) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<string> $localFiles
     * @param list<array<string, mixed>> $dbMigrations
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function buildUnifiedStatusRows(array $localFiles, array $dbMigrations, bool $pendingOnly, bool $ranOnly): array
    {
        $connectionDisplay = $this->connection ?? '<comment>default</comment>';

        $map = $this->mapDatabaseMigrations($dbMigrations, $connectionDisplay);
        $map = $this->mergeLocalFilesIntoMap($map, $localFiles, $connectionDisplay);

        return $this->filterAndFormatRows($map, $pendingOnly, $ranOnly);
    }

    /**
     * @param list<array<string, mixed>> $dbMigrations
     *
     * @return array<string, array{path: string, status: string, batch: string, connection: string, is_ran: bool}>
     */
    private function mapDatabaseMigrations(array $dbMigrations, string $connectionDisplay): array
    {
        $map = [];

        foreach ($dbMigrations as $ran) {
            $migration = $ran['migration'] ?? null;
            $batch = $ran['batch'] ?? 0;

            if (\is_string($migration)) {
                $normalizedPath = $this->normalizePath($migration);

                $batchStr = \is_int($batch) || \is_string($batch) ? (string) $batch : '0';

                $map[$normalizedPath] = [
                    'path' => $normalizedPath,
                    'status' => '<info>✓ Ran (Pruned)</info>', // Assumed pruned until proven otherwise
                    'batch' => $batchStr,
                    'connection' => $connectionDisplay,
                    'is_ran' => true,
                ];
            }
        }

        return $map;
    }

    /**
     * @param array<string, array{path: string, status: string, batch: string, connection: string, is_ran: bool}> $map
     * @param list<string> $localFiles
     *
     * @return array<string, array{path: string, status: string, batch: string, connection: string, is_ran: bool}>
     */
    private function mergeLocalFilesIntoMap(array $map, array $localFiles, string $connectionDisplay): array
    {
        foreach ($localFiles as $file) {
            $relativePath = $this->getRelativeMigrationPath($file, $this->connection);
            $normalizedPath = $this->normalizePath($relativePath);

            if (isset($map[$normalizedPath])) {
                // It is in the DB and the file exists locally
                $map[$normalizedPath]['status'] = '<info>✓ Ran</info>';
            } else {
                // It is a local file but not in the DB
                $map[$normalizedPath] = [
                    'path' => $normalizedPath,
                    'status' => '<comment>Pending</comment>',
                    'batch' => '-',
                    'connection' => $connectionDisplay,
                    'is_ran' => false,
                ];
            }
        }

        return $map;
    }

    /**
     * @param array<string, array{path: string, status: string, batch: string, connection: string, is_ran: bool}> $map
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function filterAndFormatRows(array $map, bool $pendingOnly, bool $ranOnly): array
    {
        $rows = [];

        foreach ($map as $data) {
            if ($pendingOnly && $data['is_ran']) {
                continue;
            }
            if ($ranOnly && ! $data['is_ran']) {
                continue;
            }

            $rows[] = [
                $data['path'],
                $data['status'],
                $data['batch'],
                $data['connection'],
            ];
        }

        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

        return $rows;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function displayRowsOrEmptyMessage(array $rows, bool $pendingOnly, bool $ranOnly): bool
    {
        if (\count($rows) > 0) {
            return true;
        }

        if ($pendingOnly) {
            $this->io->success('No pending migrations');
        } elseif ($ranOnly) {
            $this->io->warning('No completed migrations');
        }

        return false;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function displayStatusTable(array $rows): void
    {
        $hasNestedMigrations = $this->hasNestedStructureFromRows($rows);

        if ($hasNestedMigrations) {
            $this->displayGroupedStatus($rows);
        } else {
            $this->io->table(['Migration', 'Status', 'Batch', 'Connection'], $rows);
        }
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function hasNestedStructureFromRows(array $rows): bool
    {
        foreach ($rows as $row) {
            if (str_contains($row[0], '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $migrationFiles
     *
     * @return list<string>
     */
    private function filterMigrationsByConnection(array $migrationFiles, string $connection): array
    {
        $filtered = [];

        foreach ($migrationFiles as $file) {
            if ($this->shouldIncludeMigrationForConnection($file, $connection)) {
                $filtered[] = $file;
            }
        }

        return $filtered;
    }

    private function shouldIncludeMigrationForConnection(string $file, string $connection): bool
    {
        $migrationConnection = $this->getMigrationConnection($file);

        if ($migrationConnection === $connection) {
            return true;
        }

        if ($migrationConnection === null && $connection === 'default') {
            return true;
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', trim($path, '/\\'));
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function displayGroupedStatus(array $rows): void
    {
        $grouped = $this->groupRowsByDirectory($rows);
        ksort($grouped);

        foreach ($grouped as $directory => $migrations) {
            $this->io->section($directory);
            $this->io->table(['Migration', 'Status', 'Batch', 'Connection'], $migrations);
        }
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     *
     * @return array<string, list<array{0: string, 1: string, 2: string, 3: string}>>
     */
    private function groupRowsByDirectory(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $path = $row[0];
            $directory = $this->getDirectoryLabel($path);

            if (! isset($grouped[$directory])) {
                $grouped[$directory] = [];
            }

            $grouped[$directory][] = [
                basename($path),
                $row[1],
                $row[2],
                $row[3],
            ];
        }

        return $grouped;
    }

    private function getDirectoryLabel(string $path): string
    {
        $directory = \dirname($path);

        return $directory === '.' ? '(root)' : $directory;
    }

    private function getMigrationConnection(string $file): ?string
    {
        try {
            if (! file_exists($file)) {
                return null;
            }

            $migration = require $file;

            if (! \is_object($migration)) {
                return null;
            }

            if (! method_exists($migration, 'getConnection')) {
                return null;
            }

            $connection = $migration->getConnection();

            return \is_string($connection) ? $connection : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function displaySummary(array $rows): void
    {
        $stats = $this->calculateSummaryStats($rows);

        $this->io->newLine();
        $this->io->writeln([
            "Total migrations: <info>{$stats['total']}</info>",
            "Completed: <info>{$stats['ran']}</info>",
            "Pending: <comment>{$stats['pending']}</comment>",
        ]);

        if (\count($stats['connectionCounts']) > 1) {
            $this->displayConnectionBreakdown($stats['connectionCounts']);
        }
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     *
     * @return array{total: int, ran: int, pending: int, connectionCounts: array<string, int>}
     */
    private function calculateSummaryStats(array $rows): array
    {
        $total = \count($rows);
        $ran = 0;
        $pending = 0;
        $connectionCounts = [];

        foreach ($rows as $row) {
            if (str_contains($row[1], 'Ran')) {
                $ran++;
            } else {
                $pending++;
            }

            $connection = strip_tags($row[3]);
            if (! isset($connectionCounts[$connection])) {
                $connectionCounts[$connection] = 0;
            }
            $connectionCounts[$connection]++;
        }

        return [
            'total' => $total,
            'ran' => $ran,
            'pending' => $pending,
            'connectionCounts' => $connectionCounts,
        ];
    }

    /**
     * @param array<string, int> $connectionCounts
     */
    private function displayConnectionBreakdown(array $connectionCounts): void
    {
        $this->io->newLine();
        $this->io->writeln('<comment>By connection:</comment>');

        foreach ($connectionCounts as $conn => $count) {
            $this->io->writeln("  {$conn}: <info>{$count}</info>");
        }
    }

    private function handleError(\Throwable $e): void
    {
        $this->io->error('Failed to get migration status: ' . $e->getMessage());
        if ($this->io->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }
}
