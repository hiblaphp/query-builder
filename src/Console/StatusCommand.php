<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCommand extends Command
{
    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    /**
     * @var array<string, string>
     */
    private array $configFiles = [
        'hibla-database.php' => 'Config File',
        'hibla-migrations.php' => 'Schema File',
    ];

    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Check PDO Query Builder configuration status')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('PDO Query Builder - Status');

        $this->projectRoot = Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return Command::FAILURE;
        }

        $this->displayStatusTable();

        if (! $this->allRequiredFilesExist()) {
            $this->io->note('Run: ./vendor/bin/hibla-query-builder init');

            return Command::FAILURE;
        }

        $this->io->success('All configured!');

        return Command::SUCCESS;
    }

    private function displayStatusTable(): void
    {
        $rows = $this->buildStatusRows();
        $this->io->table(['Item', 'Status'], $rows);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function buildStatusRows(): array
    {
        if ($this->projectRoot === null) {
            throw new \LogicException('Project root must be initialized before building status rows.');
        }

        $rows = [
            ['Project Root', $this->projectRoot],
        ];

        foreach ($this->configFiles as $filename => $label) {
            $filePath = $this->getConfigFilePath($filename);
            $status = file_exists($filePath) ? '✓ Found' : '✗ Missing';
            $rows[] = [$label, $status];
        }

        $envFile = $this->projectRoot . '/.env';
        $envStatus = file_exists($envFile) ? '✓ Found' : '✗ Missing';
        $rows[] = ['.env File', $envStatus];

        return $rows;
    }

    private function allRequiredFilesExist(): bool
    {
        foreach ($this->configFiles as $filename => $label) {
            $filePath = $this->getConfigFilePath($filename);
            if (! file_exists($filePath)) {
                return false;
            }
        }

        return true;
    }

    private function getConfigFilePath(string $filename): string
    {
        if ($this->projectRoot === null) {
            throw new \LogicException('Project root must be initialized before getting config file path.');
        }

        return $this->projectRoot . '/' . $filename;
    }
}
