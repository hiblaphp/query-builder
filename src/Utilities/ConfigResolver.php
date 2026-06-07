<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

use Rcalicdan\ConfigLoader\Config;

use function Rcalicdan\ConfigLoader\env;

/**
 * @internal Resolves configuration files across the Hibla ecosystem using a fallback cascade.
 */
final class ConfigResolver
{
    /**
     * Array to hold mock configurations for testing isolation.
     *
     * @var array<string, array<string, mixed>|null>|null
     */
    public static ?array $mocks = null;

    /**
     * Resolves configuration using the cascade: ENV -> config/ dir -> root fallback.
     *
     * @param string $defaultName The default configuration name (e.g., 'hibla-database')
     * @param string $envKey The environment variable key for overrides
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(string $defaultName, string $envKey): ?array
    {
        // Check Environment Variable override
        $envPath = env($envKey);
        if (\is_string($envPath) && trim($envPath) !== '') {
            $config = Config::loadFromRoot($envPath);
            if (\is_array($config)) {
                /** @var array<string, mixed> $config */
                return $config;
            }
        }

        // Check auto-loaded config/ directory
        if (Config::has($defaultName)) {
            $config = Config::get($defaultName);
            if (\is_array($config)) {
                /** @var array<string, mixed> $config */
                return $config;
            }
        }

        // Fallback to legacy root location
        $config = Config::loadFromRoot($defaultName);
        if (\is_array($config)) {
            /** @var array<string, mixed> $config */
            return $config;
        }

        return null;
    }

    /**
     * Safely retrieve the database configuration.
     *
     * @return array<string, mixed>|null
     */
    public static function getDatabaseConfig(): ?array
    {
        if (self::$mocks !== null && \array_key_exists('database', self::$mocks)) {
            return self::$mocks['database'];
        }

        return self::resolve('hibla-database', 'HIBLA_DB_CONFIG');
    }

    /**
     * Safely retrieve the migrations configuration.
     *
     * @return array<string, mixed>|null
     */
    public static function getMigrationsConfig(): ?array
    {
        if (self::$mocks !== null && \array_key_exists('migrations', self::$mocks)) {
            return self::$mocks['migrations'];
        }

        return self::resolve('hibla-migrations', 'HIBLA_MIGRATIONS_CONFIG');
    }

    /**
     * Safely retrieve the migrations configuration.
     *
     * @return array<string, mixed>|null
     */
    public static function getSeedersConfig(): ?array
    {
        if (self::$mocks !== null && \array_key_exists('seeders', self::$mocks)) {
            return self::$mocks['seeders'];
        }

        return self::resolve('hibla-seeders', 'HIBLA_SEEDERS_CONFIG');
    }
}
