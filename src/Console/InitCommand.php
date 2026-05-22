<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    private bool $force;

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize Hibla Database configuration')
            ->setHelp('Copies the default configuration files directly to your project\'s root directory.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->force = (bool) $input->getOption('force');

        $this->io->title('Hibla Database - Initialize');

        $this->projectRoot = Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return Command::FAILURE;
        }

        if ($this->copyConfigFiles($this->projectRoot) === Command::FAILURE) {
            return Command::FAILURE;
        }

        $this->createHiblaDbExecutable();

        $this->promptEnvFileCreation();

        return Command::SUCCESS;
    }

    private function copyConfigFiles(string $targetDir): int
    {
        $files = [
            'hibla-database.php' => $this->getSourceConfigPath('hibla-database.php'),
            'hibla-migrations.php' => $this->getSourceConfigPath('hibla-migrations.php'),
        ];

        $copiedFiles = [];
        $skippedFiles = [];
        $failedFiles = [];

        foreach ($files as $filename => $sourceConfig) {
            $result = $this->copyFile($filename, $sourceConfig, $targetDir);

            if ($result === 'copied') {
                $copiedFiles[] = $filename;
            } elseif ($result === 'skipped') {
                $skippedFiles[] = $filename;
            } else {
                $failedFiles[] = $filename;
            }
        }

        foreach ($copiedFiles as $filename) {
            $this->io->success("✓ Configuration created in project root: {$filename}");
        }

        return \count($failedFiles) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function copyFile(string $filename, string $sourceConfig, string $targetDir): string
    {
        if (! file_exists($sourceConfig)) {
            $this->io->error("Source config template not found: {$sourceConfig}");

            return 'failed';
        }

        $destConfig = $targetDir . '/' . $filename;

        if (file_exists($destConfig) && ! $this->force) {
            if (! $this->io->confirm("File '{$filename}' already exists in your root folder. Overwrite?", false)) {
                $this->io->warning("Skipped: {$filename}");

                return 'skipped';
            }
        }

        if (! copy($sourceConfig, $destConfig)) {
            $this->io->error("Failed to copy {$filename} to root");

            return 'failed';
        }

        return 'copied';
    }

    private function createHiblaDbExecutable(): void
    {
        $executablePath = $this->projectRoot . '/hibla-db';

        if (file_exists($executablePath) && ! $this->force) {
            $this->io->warning('hibla-db file already exists. Use --force to overwrite.');

            return;
        }

        $stub = $this->getHiblaDbStub();

        if (file_put_contents($executablePath, $stub) === false) {
            $this->io->error('Failed to create hibla-db file');

            return;
        }

        if (DIRECTORY_SEPARATOR === '/') {
            chmod($executablePath, 0755);
        }

        $this->io->success('✓ Created hibla-db executable in project root');
        $this->io->section('Usage:');
        $this->io->listing([
            'php hibla-db init',
            'php hibla-db migrate',
            'php hibla-db make:migration create_users_table',
            'php hibla-db migrate:rollback',
            'php hibla-db migrate:status',
        ]);
    }

    private function getHiblaDbStub(): string
    {
        return <<<'PHP'
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Hibla\QueryBuilder\Console\InitCommand;
use Hibla\QueryBuilder\Console\PublishTemplatesCommand;
use Hibla\QueryBuilder\Console\MakeMigrationCommand;
use Hibla\QueryBuilder\Console\MigrateCommand;
use Hibla\QueryBuilder\Console\MigrateRollbackCommand;
use Hibla\QueryBuilder\Console\MigrateResetCommand;
use Hibla\QueryBuilder\Console\MigrateRefreshCommand;
use Hibla\QueryBuilder\Console\MigrateFreshCommand;
use Hibla\QueryBuilder\Console\MigrateStatusCommand;
use Hibla\QueryBuilder\Console\StatusCommand;

$application = new Application('Hibla Database CLI', '1.0.0');

$application->add(new InitCommand());
$application->add(new PublishTemplatesCommand());
$application->add(new MakeMigrationCommand());
$application->add(new MigrateCommand());
$application->add(new MigrateRollbackCommand());
$application->add(new MigrateResetCommand());
$application->add(new MigrateRefreshCommand());
$application->add(new MigrateFreshCommand());
$application->add(new MigrateStatusCommand());
$application->add(new StatusCommand());

$application->run();

PHP;
    }

    private function promptEnvFileCreation(): void
    {
        if ($this->projectRoot !== null && ! file_exists($this->projectRoot . '/.env')) {
            $this->io->section('Create .env file in project root with:');
            $this->io->listing([
                'DB_CONNECTION=mysql',
                'DB_HOST=127.0.0.1',
                'DB_PORT=3306',
                'DB_DATABASE=test',
                'DB_USERNAME=root',
                'DB_PASSWORD=',
            ]);
        }
    }

    /**
      * Get the absolute path to the configuration templates inside this package.
      */
    private function getSourceConfigPath(string $filename): string
    {
        return \dirname(__DIR__, 2) . '/' . $filename;
    }
}
