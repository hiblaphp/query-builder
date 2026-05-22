<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Hibla\QueryBuilder\Console\Traits\FindProjectRoot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    use FindProjectRoot;

    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    private bool $force;

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize PDO Query Builder configuration')
            ->setHelp('Copies the default configuration files to your project\'s config directory.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->force = (bool) $input->getOption('force');

        $this->io->title('PDO Query Builder - Initialize');

        $this->projectRoot = $this->findProjectRoot();
        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root');

            return Command::FAILURE;
        }

        $configDir = $this->ensureConfigDirectoryExists();
        if ($configDir === null) {
            return Command::FAILURE;
        }

        if ($this->copyConfigFiles($configDir) === Command::FAILURE) {
            return Command::FAILURE;
        }

        $this->createAsyncPdoExecutable();

        $this->promptEnvFileCreation();

        return Command::SUCCESS;
    }

    private function ensureConfigDirectoryExists(): ?string
    {
        $configDir = $this->projectRoot . '/config';
        if (! is_dir($configDir) && ! mkdir($configDir, 0755, true)) {
            $this->io->error('Failed to create config directory');

            return null;
        }

        return $configDir;
    }

    private function copyConfigFiles(string $configDir): int
    {
        $files = [
            'async-database.php' => $this->getSourceConfigPath('async-database.php'),
            'async-migrations.php' => $this->getSourceConfigPath('async-migrations.php'),
        ];

        $copiedFiles = [];
        $skippedFiles = [];
        $failedFiles = [];

        foreach ($files as $filename => $sourceConfig) {
            $result = $this->copyFile($filename, $sourceConfig, $configDir);

            if ($result === 'copied') {
                $copiedFiles[] = $filename;
            } elseif ($result === 'skipped') {
                $skippedFiles[] = $filename;
            } else {
                $failedFiles[] = $filename;
            }
        }

        foreach ($copiedFiles as $filename) {
            $this->io->success("✓ Configuration created: config/{$filename}");
        }

        return \count($failedFiles) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function copyFile(string $filename, string $sourceConfig, string $configDir): string
    {
        if (! file_exists($sourceConfig)) {
            $this->io->error("Source config not found: {$sourceConfig}");

            return 'failed';
        }

        $destConfig = $configDir . '/' . $filename;

        if (file_exists($destConfig) && ! $this->force) {
            if (! $this->io->confirm("File '{$filename}' already exists. Overwrite?", false)) {
                $this->io->warning("Skipped: {$filename}");

                return 'skipped';
            }
        }

        if (! copy($sourceConfig, $destConfig)) {
            $this->io->error("Failed to copy {$filename}");

            return 'failed';
        }

        return 'copied';
    }

    private function createAsyncPdoExecutable(): void
    {
        $asyncPdoPath = $this->projectRoot . '/db';

        if (file_exists($asyncPdoPath) && ! $this->force) {
            $this->io->warning('db file already exists. Use --force to overwrite.');

            return;
        }

        $stub = $this->getAsyncPdoStub();

        if (file_put_contents($asyncPdoPath, $stub) === false) {
            $this->io->error('Failed to create db file');

            return;
        }

        if (DIRECTORY_SEPARATOR === '/') {
            chmod($asyncPdoPath, 0755);
        }

        $this->io->success('✓ Created db executable');
        $this->io->section('Usage:');
        $this->io->listing([
            'php db init',
            'php db publish:templates',
            'php db migrate',
            'php db make:migration create_users_table',
            'php db migrate:rollback',
            'php db migrate:status',
        ]);
    }

    private function getAsyncPdoStub(): string
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

$application = new Application('Hibla Query Builder', '1.0.0');

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
            $this->io->section('Create .env file with:');
            $this->io->listing([
                'DB_CONNECTION=mysql',
                'DB_HOST=127.0.0.1',
                'DB_PORT=3306',
                'DB_DATABASE=your_database',
                'DB_USERNAME=root',
                'DB_PASSWORD=',
            ]);
        }
    }

    private function getSourceConfigPath(string $filename): string
    {
        $paths = [
            __DIR__ . "/../../config/{$filename}",
            __DIR__ . "/../../../config/{$filename}",
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return __DIR__ . "/../../config/{$filename}";
    }
}
