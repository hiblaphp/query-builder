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
        } catch (InvalidArgumentException $e) {
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
        $migrationFiles = $this->loadMigrationFiles($path);

        if ($migrationFiles === null) {
            return;
        }

        $migrationFiles = $this->applyConnectionFilter($migrationFiles);

        if ($migrationFiles === null) {
            return;
        }

        $ranMigrationsByConnection = $this->getRanMigrationsForAllConnections($migrationFiles);

        $rows = $this->buildStatusRowsWithMultipleConnections($migrationFiles, $ranMigrationsByConnection, $pendingOnly, $ranOnly);

        if (! $this->displayRowsOrEmptyMessage($rows, $pendingOnly, $ranOnly)) {
            return;
        }

        $this->displayStatusTable($migrationFiles, $rows);
        $this->displaySummary($rows);
    }

    /**
     * @return list<string>|null
     */
    private function loadMigrationFiles(?string $path): ?array
    {
        if ($path !== null) {
            $pattern = rtrim($path, '/') . '/*.php';
            $migrationFiles = $this->getFilteredMigrationFiles($pattern, $this->connection);

            if (\count($migrationFiles) === 0) {
                $this->io->warning("No migration files found in path: {$path}");

                return null;
            }

            $this->io->note("Showing migrations from path: {$path}");

            return $migrationFiles;
        }

        $migrationFiles = $this->getAllMigrationFiles($this->connection);

        if (\count($migrationFiles) === 0) {
            $this->io->warning('No migration files found');

            return null;
        }

        return $migrationFiles;
    }

    /**
     * @param list<string> $migrationFiles
     *
     * @return list<string>|null
     */
    private function applyConnectionFilter(array $migrationFiles): ?array
    {
        if ($this->connection === null) {
            return $migrationFiles;
        }

        $filtered = $this->filterMigrationsByConnection($migrationFiles, $this->connection);

        if (\count($filtered) === 0) {
            $this->io->warning("No migrations found for connection: {$this->connection}");

            return null;
        }

        return $filtered;
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
     * @param list<string> $migrationFiles
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function displayStatusTable(array $migrationFiles, array $rows): void
    {
        $hasNestedMigrations = $this->hasNestedStructure($migrationFiles);

        if ($hasNestedMigrations) {
            $this->displayGroupedStatus($rows);
        } else {
            $this->io->table(['Migration', 'Status', 'Batch', 'Connection'], $rows);
        }
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

    /**
     * @param list<string> $migrationFiles
     *
     * @return array<string, array<string, int>>
     */
    private function getRanMigrationsForAllConnections(array $migrationFiles): array
    {
        $connectionsToCheck = $this->extractUniqueConnections($migrationFiles);
        $ranByConnection = [];

        foreach ($connectionsToCheck as $connectionKey => $connectionName) {
            $ranByConnection[$connectionKey] = $this->getRanMigrationsForConnection($connectionName);
        }

        return $ranByConnection;
    }

    /**
     * @param list<string> $migrationFiles
     *
     * @return array<string, string|null>
     */
    private function extractUniqueConnections(array $migrationFiles): array
    {
        $connectionsToCheck = [];

        foreach ($migrationFiles as $file) {
            $migrationConnection = $this->getMigrationConnection($file);
            $connectionKey = $migrationConnection ?? 'default';
            $connectionsToCheck[$connectionKey] = $migrationConnection;
        }

        return $connectionsToCheck;
    }

    /**
     * @return array<string, int>
     */
    private function getRanMigrationsForConnection(?string $connectionName): array
    {
        $repository = new MigrationRepository(
            $this->getMigrationsTable($connectionName),
            $connectionName
        );

        try {
            await($repository->createRepository());
            /** @var list<array<string, mixed>> $ranMigrations */
            $ranMigrations = await($repository->getRan());

            return $this->buildRanMigrationMap($ranMigrations);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $ranMigrations
     *
     * @return array<string, int>
     */
    private function buildRanMigrationMap(array $ranMigrations): array
    {
        $ranMap = [];

        foreach ($ranMigrations as $migration) {
            $path = $migration['migration'] ?? null;
            $batch = $migration['batch'] ?? null;

            if (\is_string($path)) {
                $normalizedPath = $this->normalizePath($path);
                $ranMap[$normalizedPath] = is_numeric($batch) ? (int) $batch : 0;
            }
        }

        return $ranMap;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', trim($path, '/\\'));
    }

    /**
     * @param list<string> $migrationFiles
     * @param array<string, array<string, int>> $ranMigrationsByConnection
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function buildStatusRowsWithMultipleConnections(
        array $migrationFiles,
        array $ranMigrationsByConnection,
        bool $pendingOnly,
        bool $ranOnly
    ): array {
        $rows = [];

        foreach ($migrationFiles as $file) {
            $row = $this->buildStatusRow($file, $ranMigrationsByConnection, $pendingOnly, $ranOnly);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, array<string, int>> $ranMigrationsByConnection
     *
     * @return array{0: string, 1: string, 2: string, 3: string}|null
     */
    private function buildStatusRow(
        string $file,
        array $ranMigrationsByConnection,
        bool $pendingOnly,
        bool $ranOnly
    ): ?array {
        $relativePath = $this->getRelativeMigrationPath($file, $this->connection);
        $normalizedRelativePath = $this->normalizePath($relativePath);

        $migrationConnection = $this->getMigrationConnection($file);
        $connectionKey = $migrationConnection ?? 'default';
        $connectionDisplay = $migrationConnection ?? '<comment>default</comment>';

        $ranMap = $ranMigrationsByConnection[$connectionKey] ?? [];
        $isRan = \array_key_exists($normalizedRelativePath, $ranMap);

        if (! $this->shouldIncludeInResults($isRan, $pendingOnly, $ranOnly)) {
            return null;
        }

        return $this->formatStatusRow($relativePath, $isRan, $ranMap, $normalizedRelativePath, $connectionDisplay);
    }

    private function shouldIncludeInResults(bool $isRan, bool $pendingOnly, bool $ranOnly): bool
    {
        if ($pendingOnly && $isRan) {
            return false;
        }

        if ($ranOnly && ! $isRan) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, int> $ranMap
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function formatStatusRow(
        string $relativePath,
        bool $isRan,
        array $ranMap,
        string $normalizedRelativePath,
        string $connectionDisplay
    ): array {
        if ($isRan) {
            $batch = $ranMap[$normalizedRelativePath];
            $batchStr = $batch > 0 ? (string) $batch : 'N/A';

            return [$relativePath, '<info>✓ Ran</info>', $batchStr, $connectionDisplay];
        }

        return [$relativePath, '<comment>Pending</comment>', '-', $connectionDisplay];
    }

    /**
     * @param list<string> $migrationFiles
     */
    private function hasNestedStructure(array $migrationFiles): bool
    {
        foreach ($migrationFiles as $file) {
            $relativePath = $this->getRelativeMigrationPath($file, $this->connection);
            if (str_contains($relativePath, '/')) {
                return true;
            }
        }

        return false;
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
