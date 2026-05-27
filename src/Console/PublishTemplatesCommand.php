<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console;

use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublishTemplatesCommand extends Command
{
    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    private bool $force;

    protected function configure(): void
    {
        $this
            ->setName('publish:templates')
            ->setDescription('Publish pagination templates to the configured location')
            ->setHelp('Publishes pagination templates to the path specified in hibla-database.php')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing templates')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Custom path to publish templates (overrides config)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->force = (bool) $input->getOption('force');

        $this->io->title('Publish Pagination Templates');

        $this->projectRoot = Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return Command::FAILURE;
        }

        $targetPath = $this->determineTargetPath($input);

        if ($targetPath === null) {
            $this->displayConfigurationError();

            return Command::FAILURE;
        }

        $this->io->info("Publishing templates to: {$targetPath}");

        if (! $this->ensureDirectoryExists($targetPath)) {
            return Command::FAILURE;
        }

        $published = $this->publishTemplates($targetPath);

        if ($published > 0) {
            $this->io->success("✓ Published {$published} template(s) successfully!");
            $this->showNextSteps($targetPath);
        }

        return Command::SUCCESS;
    }

    private function determineTargetPath(InputInterface $input): ?string
    {
        $customPath = $input->getOption('path');

        if (\is_string($customPath) && $customPath !== '') {
            return $this->resolveCustomPath($customPath);
        }

        return $this->getConfiguredPath();
    }

    private function displayConfigurationError(): void
    {
        $this->io->error('No templates path configured. Please set pagination.templates_path in hibla-database.php');
        $this->io->note('Example: \'templates_path\' => __DIR__ . \'/../resources/views/pagination\'');
    }

    private function getConfiguredPath(): ?string
    {
        try {
            $dbConfig = Config::loadFromRoot('hibla-database');

            if (! \is_array($dbConfig)) {
                return null;
            }

            /** @var array<string, mixed> $typedConfig */
            $typedConfig = $dbConfig;

            $templatesPath = $this->extractTemplatesPathFromConfig($typedConfig);

            return $this->validateTemplatesPath($templatesPath);
        } catch (\Throwable $e) {
            $this->io->warning("Could not load config: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @param array<string, mixed> $dbConfig
     */
    private function extractTemplatesPathFromConfig(array $dbConfig): mixed
    {
        $paginationConfig = $dbConfig['pagination'] ?? [];

        if (! \is_array($paginationConfig)) {
            return null;
        }

        return $paginationConfig['templates_path'] ?? null;
    }

    private function validateTemplatesPath(mixed $templatesPath): ?string
    {
        if (! \is_string($templatesPath)) {
            return null;
        }

        if (trim($templatesPath) === '') {
            return null;
        }

        return $templatesPath;
    }

    private function resolveCustomPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->projectRoot . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }

    private function ensureDirectoryExists(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (! $this->createDirectory($path)) {
            return false;
        }

        $this->io->info("✓ Created directory: {$path}");

        return true;
    }

    private function createDirectory(string $path): bool
    {
        if (! mkdir($path, 0755, true)) {
            $this->io->error("Failed to create directory: {$path}");

            return false;
        }

        return true;
    }

    private function publishTemplates(string $targetPath): int
    {
        $sourceTemplatesDir = $this->getSourceTemplatesPath();

        if (! $this->validateSourceDirectory($sourceTemplatesDir)) {
            return 0;
        }

        $templates = $this->getTemplateList();

        return $this->copyTemplates($sourceTemplatesDir, $targetPath, $templates);
    }

    private function validateSourceDirectory(string $sourceTemplatesDir): bool
    {
        if (is_dir($sourceTemplatesDir)) {
            return true;
        }

        $this->io->error("Source templates directory not found: {$sourceTemplatesDir}");
        $this->io->note('Attempted paths for debugging:');
        $this->debugTemplatePaths();

        return false;
    }

    /**
     * @return list<string>
     */
    private function getTemplateList(): array
    {
        return [
            'bootstrap.php',
            'tailwind.php',
            'simple.php',
            'cursor-simple.php',
            'cursor-bootstrap.php',
            'cursor-tailwind.php',
        ];
    }

    /**
     * @param list<string> $templates
     */
    private function copyTemplates(string $sourceTemplatesDir, string $targetPath, array $templates): int
    {
        $copiedCount = 0;

        $this->io->section('Publishing Templates:');

        foreach ($templates as $template) {
            if ($this->copyTemplate($sourceTemplatesDir, $targetPath, $template)) {
                $copiedCount++;
            }
        }

        return $copiedCount;
    }

    private function copyTemplate(string $sourceTemplatesDir, string $targetPath, string $template): bool
    {
        $source = $sourceTemplatesDir . DIRECTORY_SEPARATOR . $template;
        $destination = $targetPath . DIRECTORY_SEPARATOR . $template;

        if (! file_exists($source)) {
            $this->io->warning("Source not found: {$template}");

            return false;
        }

        if (! $this->shouldOverwriteTemplate($destination, $template)) {
            $this->io->text("  <comment>⊘</comment> Skipped: {$template}");

            return false;
        }

        return $this->performCopy($source, $destination, $template);
    }

    private function shouldOverwriteTemplate(string $destination, string $template): bool
    {
        if (! file_exists($destination)) {
            return true;
        }

        if ($this->force) {
            return true;
        }

        return $this->io->confirm("  Template '{$template}' already exists. Overwrite?", false);
    }

    private function performCopy(string $source, string $destination, string $template): bool
    {
        if (copy($source, $destination)) {
            $this->io->text("  <info>✓</info> Published: {$template}");

            return true;
        }

        $this->io->text("  <error>✗</error> Failed: {$template}");

        return false;
    }

    /**
     * Show next steps after publishing
     */
    private function showNextSteps(string $targetPath): void
    {
        $this->io->section('Next Steps:');

        $this->displayUsageInstructions($targetPath);
        $this->displayAvailableTemplates();
        $this->displayCodeExample();
    }

    private function displayUsageInstructions(string $targetPath): void
    {
        $this->io->listing([
            'Templates have been published to: ' . $targetPath,
            'Customize the templates to fit your design needs',
            'Templates will be automatically loaded from this location',
        ]);
    }

    private function displayAvailableTemplates(): void
    {
        $this->io->note([
            'Available templates:',
            '  - bootstrap.php     → Bootstrap 5 styled pagination',
            '  - tailwind.php      → Tailwind CSS styled pagination',
            '  - simple.php        → Simple text-based pagination',
            '  - cursor-simple.php → Simple cursor-based pagination',
            '  - cursor-bootstrap.php → Bootstrap 5 styled cursor pagination',
            '  - cursor-tailwind.php → Tailwind CSS styled cursor pagination',
        ]);
    }

    private function displayCodeExample(): void
    {
        $this->io->text([
            '',
            'Usage in your code:',
            '  $paginator = await(DB::table(\'users\')->paginate(15));',
            '  echo $paginator->render(\'bootstrap\'); // Uses your custom template',
        ]);
    }

    /**
     * Get source templates path from vendor directory
     * FIXED: Better path resolution for Windows/Unix compatibility
     */
    private function getSourceTemplatesPath(): string
    {
        $paths = $this->buildTemplatePaths();

        foreach ($paths as $path) {
            $normalizedPath = $this->normalizePath($path);
            if (is_dir($normalizedPath)) {
                return $normalizedPath;
            }
        }

        return $paths[0];
    }

    /**
     * @return list<string>
     */
    private function buildTemplatePaths(): array
    {
        return [
            $this->buildVendorPath(),
            $this->buildRelativePath(\dirname(__DIR__)),
            $this->buildRelativePath(\dirname(__DIR__, 2)),
            $this->buildRelativePath(__DIR__ . DIRECTORY_SEPARATOR . '..'),
        ];
    }

    private function buildVendorPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'hiblaphp'
            . DIRECTORY_SEPARATOR . 'query-builder' . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates';
    }

    private function buildRelativePath(string $basePath): string
    {
        return $basePath . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates';
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Debug helper to show all attempted template paths
     */
    private function debugTemplatePaths(): void
    {
        $paths = $this->getDebugPaths();

        foreach ($paths as $label => $path) {
            $this->displayDebugPath($label, $path);
        }
    }

    /**
     * @return array<string, string>
     */
    private function getDebugPaths(): array
    {
        return [
            'Project vendor' => $this->buildVendorPath(),
            'Package development' => $this->buildRelativePath(\dirname(__DIR__)),
            'Alternative location' => $this->buildRelativePath(\dirname(__DIR__, 2)),
            'Relative path' => $this->buildRelativePath(__DIR__ . DIRECTORY_SEPARATOR . '..'),
        ];
    }

    private function displayDebugPath(string $label, string $path): void
    {
        $normalizedPath = $this->normalizePath($path);
        $exists = is_dir($normalizedPath) ? '✓ EXISTS' : '✗ NOT FOUND';
        $this->io->text("  {$exists} [{$label}]: {$normalizedPath}");
    }
}
