<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Hibla\QueryBuilder\Console\Traits\ValidateConnection;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateRefreshCommand extends Command
{
    use ValidateConnection;

    private SymfonyStyle $io;

    private OutputInterface $output;

    private ?string $connection = null;

    private ?string $path = null;

    private ?int $step = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:refresh')
            ->setDescription('Reset and re-run all migrations')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migrations files')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to rollback')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation without confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeCommand($input, $output);
        $this->io->title('Refresh Migrations');

        $this->extractOptions($input);
        $this->displayOptions();

        try {
            $this->validateConnection($this->connection);
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (! $this->shouldProceed($input)) {
            $this->io->info('Operation cancelled');

            return Command::SUCCESS;
        }

        return $this->performRefresh();
    }

    private function initializeCommand(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
    }

    private function extractOptions(InputInterface $input): void
    {
        $this->connection = $this->extractStringOption($input, 'connection');
        $this->path = $this->extractStringOption($input, 'path');
        $this->step = $this->extractStepOption($input);
    }

    private function extractStringOption(InputInterface $input, string $optionName): ?string
    {
        $option = $input->getOption($optionName);

        return (\is_string($option) && $option !== '') ? $option : null;
    }

    private function extractStepOption(InputInterface $input): ?int
    {
        $stepOption = $input->getOption('step');

        return is_numeric($stepOption) ? (int) $stepOption : null;
    }

    private function displayOptions(): void
    {
        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

        if ($this->path !== null) {
            $this->io->note("Using migration path: {$this->path}");
        }
    }

    private function shouldProceed(InputInterface $input): bool
    {
        if ((bool) $input->getOption('force')) {
            return true;
        }

        return $this->confirmRefresh();
    }

    private function confirmRefresh(): bool
    {
        $message = $this->getConfirmationMessage();

        return $this->io->confirm($message, false);
    }

    private function getConfirmationMessage(): string
    {
        if ($this->step !== null) {
            return "This will rollback the last {$this->step} migration(s) and re-run them. Continue?";
        }

        return 'This will rollback ALL migrations and re-run them. Continue?';
    }

    private function performRefresh(): int
    {
        $this->io->newLine();

        if (! $this->performReset()) {
            return Command::FAILURE;
        }

        $this->io->newLine();

        if (! $this->performMigration()) {
            return Command::FAILURE;
        }

        $this->displaySuccessMessage();

        return Command::SUCCESS;
    }

    private function performReset(): bool
    {
        $sectionTitle = $this->step !== null ? 'Rolling back migrations' : 'Resetting database';
        $this->io->section($sectionTitle);

        $resetResult = $this->resetMigrations($this->step, $this->path);

        if ($resetResult === false) {
            $this->io->error('Reset failed');

            return false;
        }

        if ($resetResult === 0) {
            $this->io->info('Nothing to reset');
        }

        return true;
    }

    private function performMigration(): bool
    {
        $this->io->section('Running migrations');

        $migrateResult = $this->runMigrations($this->path);

        if ($migrateResult === false) {
            $this->io->error('Migration failed');

            return false;
        }

        if ($migrateResult === 0) {
            $this->io->info('Nothing to migrate');
        }

        return true;
    }

    private function displaySuccessMessage(): void
    {
        $this->io->newLine();
        $this->io->success('Database refreshed successfully!');
    }

    /**
     * Reset migrations
     *
     * @return int|false Number of migrations reset (0 if nothing to reset), false on error
     */
    private function resetMigrations(?int $step, ?string $path): int|false
    {
        $commandName = $this->getResetCommandName($step);
        $bufferedOutput = new BufferedOutput();

        if (! $this->executeCommand($commandName, $step, $path, $bufferedOutput, true)) {
            return false;
        }

        return $this->processResetOutput($bufferedOutput);
    }

    private function getResetCommandName(?int $step): string
    {
        return $step !== null ? 'migrate:rollback' : 'migrate:reset';
    }

    private function processResetOutput(BufferedOutput $bufferedOutput): int
    {
        $content = $bufferedOutput->fetch();

        if ($this->isNothingToReset($content)) {
            return 0;
        }

        $count = $this->displayFilteredOutput($content, [
            'Rolling back:',
            '✓',
            'Rolled back:',
        ]);

        return $count > 0 ? $count : 1;
    }

    private function isNothingToReset(string $content): bool
    {
        return str_contains($content, 'Nothing to reset') ||
            str_contains($content, 'Nothing to rollback');
    }

    /**
     * Run migrations
     *
     * @return int|false Number of migrations run (0 if nothing to migrate), false on failure
     */
    private function runMigrations(?string $path): int|false
    {
        $bufferedOutput = new BufferedOutput();

        if (! $this->executeCommand('migrate', null, $path, $bufferedOutput, false)) {
            return false;
        }

        return $this->processMigrationOutput($bufferedOutput);
    }

    private function processMigrationOutput(BufferedOutput $bufferedOutput): int
    {
        $content = $bufferedOutput->fetch();

        if (str_contains($content, 'Nothing to migrate')) {
            return 0;
        }

        $count = $this->displayFilteredOutput($content, [
            'Migrating:',
            '✓',
            'Migrated:',
        ]);

        return $count > 0 ? $count : 1;
    }

    /**
     * Display filtered output based on keywords
     *
     * @param list<string> $keywords
     *
     * @return int Number of lines displayed
     */
    private function displayFilteredOutput(string $content, array $keywords): int
    {
        $lines = explode("\n", $content);
        $displayedCount = 0;

        foreach ($lines as $line) {
            if ($this->shouldDisplayLine($line, $keywords)) {
                $this->output->writeln($line);
                $displayedCount++;
            }
        }

        return $displayedCount;
    }

    /**
     * @param list<string> $keywords
     */
    private function shouldDisplayLine(string $line, array $keywords): bool
    {
        $trimmedLine = trim($line);

        if ($this->isEmptyLine($trimmedLine)) {
            return false;
        }

        if ($this->isSeparatorLine($trimmedLine)) {
            return false;
        }

        if ($this->isHeaderLine($trimmedLine)) {
            return false;
        }

        return $this->lineContainsKeyword($line, $keywords);
    }

    private function isEmptyLine(string $line): bool
    {
        return $line === '';
    }

    private function isSeparatorLine(string $line): bool
    {
        return preg_match('/^[=\-]+$/', $line) === 1;
    }

    private function isHeaderLine(string $line): bool
    {
        $headers = [
            'Reset Migrations',
            'Database Migrations',
            'Running migrations',
            'Preparing migration',
        ];

        foreach ($headers as $header) {
            if (str_starts_with($line, $header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $keywords
     */
    private function lineContainsKeyword(string $line, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($line, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function executeCommand(
        string $commandName,
        ?int $step = null,
        ?string $path = null,
        ?OutputInterface $customOutput = null,
        bool $forceFlag = false
    ): bool {
        $application = $this->getApplication();

        if ($application === null) {
            $this->io->error('Application instance not found');

            return false;
        }

        try {
            $command = $application->find($commandName);
            $arguments = $this->buildCommandArguments($commandName, $step, $path, $forceFlag);
            $input = new ArrayInput($arguments);
            $outputToUse = $customOutput ?? $this->output;

            return $command->run($input, $outputToUse) === Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->displayCommandError($commandName, $e);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCommandArguments(
        string $commandName,
        ?int $step,
        ?string $path,
        bool $forceFlag
    ): array {
        $arguments = [];

        if ($this->connection !== null) {
            $arguments['--connection'] = $this->connection;
        }

        if ($path !== null) {
            $arguments['--path'] = $path;
        }

        if ($step !== null && $commandName === 'migrate:rollback') {
            $arguments['--step'] = $step;
        }

        if ($forceFlag) {
            $arguments['--force'] = true;
        }

        return $arguments;
    }

    private function displayCommandError(string $commandName, \Throwable $e): void
    {
        $this->io->error("Failed to execute command '{$commandName}': " . $e->getMessage());
    }
}
