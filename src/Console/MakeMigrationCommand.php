<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Carbon\Carbon;
use Hibla\QueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeMigrationCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private ?string $projectRoot = null;
    private string $migrationsPath;
    private string $migrationName;
    private ?string $table;
    private ?string $alter;
    private ?string $connection = null;
    private ?string $subdirectory = null;

    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('Create a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name (supports subdirectories, e.g., backup/create_users_table)')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'Table to create')
            ->addOption('alter', null, InputOption::VALUE_OPTIONAL, 'Table to alter')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Custom subdirectory path for the migration')
            ->addOption('create', null, InputOption::VALUE_OPTIONAL, 'Alias for --table option')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Create Migration');

        $connectionOption = $input->getOption('connection');
        $this->connection = (\is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

        $migrationNameValue = $input->getArgument('name');
        if (! \is_string($migrationNameValue) || trim($migrationNameValue) === '') {
            $this->io->error('The migration name must be a non-empty string.');

            return Command::FAILURE;
        }

        $this->parseMigrationName($migrationNameValue);

        $tableOption = $input->getOption('table');
        $createOption = $input->getOption('create');
        $this->table = \is_string($tableOption) ? $tableOption : (\is_string($createOption) ? $createOption : null);

        $alterOption = $input->getOption('alter');
        $this->alter = \is_string($alterOption) ? $alterOption : null;

        if ($this->table === null && $this->alter === null) {
            $this->autoDetectTableOperation($migrationNameValue);
        }

        $pathOption = $input->getOption('path');
        if (\is_string($pathOption) && $pathOption !== '') {
            $this->subdirectory = trim($pathOption, '/\\');
        }

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if (! $this->ensureMigrationsDirectory()) {
            return Command::FAILURE;
        }

        if (! $this->createMigrationFile()) {
            return Command::FAILURE;
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

    private function parseMigrationName(string $input): void
    {
        $normalized = str_replace('\\', '/', $input);

        if (str_contains($normalized, '/')) {
            $parts = explode('/', $normalized);
            $this->migrationName = array_pop($parts);

            if ($this->subdirectory === null) {
                $this->subdirectory = implode(DIRECTORY_SEPARATOR, $parts);
            }
        } else {
            $this->migrationName = $input;
        }

        $this->migrationName = $this->sanitizeMigrationName($this->migrationName);
    }

    private function sanitizeMigrationName(string $name): string
    {
        $name = str_replace(['/', '\\'], '', $name);

        $name = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?? $name;
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_]/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';

        return trim($name, '_');
    }

    private function autoDetectTableOperation(string $migrationName): void
    {
        $normalized = $this->sanitizeMigrationName($migrationName);

        if (preg_match('/^create_(.+?)_table$/', $normalized, $matches) === 1) {
            $this->table = $matches[1];
            $this->io->note("Auto-detected table creation: {$this->table}");

            return;
        }

        if (preg_match('/^add_.+?_to_(.+?)(?:_table)?$/', $normalized, $matches) === 1) {
            $this->alter = $matches[1];
            $this->io->note("Auto-detected table alteration: {$this->alter}");

            return;
        }

        if (preg_match('/^remove_.+?_from_(.+?)(?:_table)?$/', $normalized, $matches) === 1) {
            $this->alter = $matches[1];
            $this->io->note("Auto-detected table alteration: {$this->alter}");

            return;
        }

        if (preg_match('/^drop_(.+?)_table$/', $normalized, $matches) === 1) {
            $this->alter = $matches[1];
            $this->io->note("Auto-detected table operation: {$this->alter}");

            return;
        }

        if (preg_match('/^(?:update|modify)_(.+?)_table$/', $normalized, $matches) === 1) {
            $this->alter = $matches[1];
            $this->io->note("Auto-detected table alteration: {$this->alter}");

            return;
        }
    }

    private function ensureMigrationsDirectory(): bool
    {
        $basePath = $this->getMigrationsPath($this->connection);

        if ($this->subdirectory !== null) {
            $this->migrationsPath = $basePath . DIRECTORY_SEPARATOR . $this->subdirectory;
        } else {
            $this->migrationsPath = $basePath;
        }

        if (! $this->ensureDirectoryExists($this->migrationsPath)) {
            $this->io->error("Failed to create migrations directory: {$this->migrationsPath}");

            return false;
        }

        return true;
    }

    private function createMigrationFile(): bool
    {
        $fileName = $this->generateFileName();
        $filePath = $this->migrationsPath . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            $this->io->error("Migration file already exists: {$fileName}");

            return false;
        }

        $stub = $this->generateMigrationStub();

        if (file_put_contents($filePath, $stub) === false) {
            $this->io->error('Failed to create migration file');

            return false;
        }

        $displayPath = $this->subdirectory !== null
            ? $this->subdirectory . DIRECTORY_SEPARATOR . $fileName
            : $fileName;

        $displayPath = str_replace('\\', '/', $displayPath);

        $this->io->success("Migration created: {$displayPath}");

        if ($this->subdirectory !== null) {
            $this->io->note("Migration organized in subdirectory: {$this->subdirectory}");
        }

        return true;
    }

    private function generateFileName(): string
    {
        $convention = $this->getNamingConvention($this->connection);

        return match ($convention) {
            'sequential' => $this->generateSequentialFileName(),
            'timestamp' => $this->generateTimestampFileName(),
            default => $this->generateTimestampFileName(),
        };
    }

    private function generateTimestampFileName(): string
    {
        $timezone = $this->getTimezone($this->connection);
        $timestamp = Carbon::now($timezone)->format('Y_m_d_His');

        return "{$timestamp}_{$this->migrationName}.php";
    }

    private function generateSequentialFileName(): string
    {
        $files = glob($this->migrationsPath . '/*.php');
        if ($files === false) {
            $files = [];
        }
        $nextNumber = \count($files) + 1;
        $paddedNumber = str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        return "{$paddedNumber}_{$this->migrationName}.php";
    }

    private function generateMigrationStub(): string
    {
        if ($this->table !== null) {
            return $this->getCreateStub();
        }

        if ($this->alter !== null) {
            return $this->getAlterStub();
        }

        return $this->getBlankStub();
    }

    private function getCreateStub(): string
    {
        $connectionLine = $this->connection !== null
            ? "    protected ?string \$connection = '{$this->connection}';\n\n"
            : '';

        return "<?php

use Hibla\QueryBuilder\Schema\Blueprint;
use Hibla\QueryBuilder\Schema\Migration;

use function Hibla\await;

return new class extends Migration
{
{$connectionLine}    /**
     * Run the migration.
     */
    public function up(): void
    {
        await(\$this->create('{$this->table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        }));
    }
    
    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        await(\$this->dropIfExists('{$this->table}'));
    }
};
";
    }

    private function getAlterStub(): string
    {
        $connectionLine = $this->connection !== null
            ? "    protected ?string \$connection = '{$this->connection}';\n\n"
            : '';

        return "<?php

use Hibla\QueryBuilder\Schema\Blueprint;
use Hibla\QueryBuilder\Schema\Migration;

use function Hibla\await;

return new class extends Migration
{
{$connectionLine}    /**
     * Run the migration.
     */
    public function up(): void
    {
        await(\$this->table('{$this->alter}', function (Blueprint \$table) {
            // Add columns, indexes, etc.
        }));
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        await(\$this->table('{$this->alter}', function (Blueprint \$table) {
            // Reverse the changes
        }));
    }
};
";
    }

    private function getBlankStub(): string
    {
        $connectionLine = $this->connection !== null
            ? "    protected ?string \$connection = '{$this->connection}';\n\n"
            : '';

        return "<?php

use Hibla\QueryBuilder\Schema\Migration;

use function Hibla\await;

return new class extends Migration
{
{$connectionLine}    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Write your migration here
        await(\$this->raw('-- Add your SQL here'));
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // Reverse your migration here
        await(\$this->raw('-- Add your rollback SQL here'));
    }
};
";
    }
}