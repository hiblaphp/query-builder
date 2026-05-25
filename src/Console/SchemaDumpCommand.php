<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Hibla\QueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\QueryBuilder\Console\Traits\ValidateConnection;
use Hibla\QueryBuilder\Schema\MigrationRepository;
use Hibla\QueryBuilder\Schema\States\SchemaState;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Hibla\await;

class SchemaDumpCommand extends Command
{
    use LoadsSchemaConfiguration;
    use ValidateConnection;

    private SymfonyStyle $io;

    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('schema:dump')
            ->setDescription('Dump the given database schema to a file')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('prune', null, InputOption::VALUE_NONE, 'Delete all existing migration files after dumping')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->connection = $input->getOption('connection') ?? null;

        $this->validateConnection($this->connection);

        $schemaConfig = $this->getSchemaConfig($this->connection);
        $schemaDirectory = $schemaConfig['schema_path'];
        $migrationsTable = $schemaConfig['migrations_table'];

        if (! is_dir($schemaDirectory)) {
            mkdir($schemaDirectory, 0755, true);
        }

        $connectionName = $this->connection ?? 'mysql';
        $path = "{$schemaDirectory}/{$connectionName}-schema.sql";

        $this->io->write("Dumping database schema to <comment>{$path}</comment>... ");

        try {
            $state = SchemaState::make($this->connection);
            $dbConfig = $this->getDatabaseConfig($this->connection);

            $state->dump($dbConfig, $path, $migrationsTable);

            $this->io->writeln('<info>✓</info>');

            if ($input->getOption('prune')) {
                $this->pruneMigrations();
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->io->newLine();
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function pruneMigrations(): void
    {
        $this->io->write('Pruning migration files... ');

        $repository = new MigrationRepository($this->getMigrationsTable($this->connection), $this->connection);

        // Ensure repository exists before trying to fetch ran migrations
        if (await($repository->repositoryExists()) === 0) {
            $this->io->writeln('<comment>No migrations to prune.</comment>');

            return;
        }

        $ranMigrations = await($repository->getRan());

        foreach ($ranMigrations as $migration) {
            $path = $this->getFullMigrationPath($migration['migration'], $this->connection);
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $this->io->writeln('<info>✓</info>');
    }

    private function getDatabaseConfig(?string $connection): array
    {
        $config = Config::loadFromRoot('hibla-database');
        $connName = $connection ?? $config['default'] ?? 'mysql';

        return $config['connections'][$connName] ?? [];
    }
}
