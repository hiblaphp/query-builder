<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Utilities;

use Rcalicdan\ConfigLoader\Config;
use function Rcalicdan\ConfigLoader\env;

/**
 * @internal Resolves configuration files across the Hibla database ecosystem using a fallback cascade.
 */
final class ConfigResolver
{
    /**
     * Resolves configuration using the cascade: ENV -> config/ dir -> root fallback.
     *
     * @param string $defaultName The default configuration name (e.g., 'hibla-database')
     * @param string $envKey The environment variable key for overrides
     * @return array<string, mixed>|null
     */
    public static function resolve(string $defaultName, string $envKey): ?array
    {
        // Check Environment Variable override (e.g., HIBLA_DB_CONFIG="database/db-config")
        $envPath = env($envKey);

        if (\is_string($envPath) && trim($envPath) !== '') {
            $config = Config::loadFromRoot($envPath);
            if (\is_array($config)) {
                return $config;
            }
        }

        // Check auto-loaded config/ directory (native to ConfigLoader)
        // If the user placed it in /config/hibla-database.php, ConfigLoader already knows about it.
        if (Config::has($defaultName)) {
            $config = Config::get($defaultName);
            if (\is_array($config)) {
                return $config;
            }
        }

        // Fallback to legacy root location
        $config = Config::loadFromRoot($defaultName);

        if (\is_array($config)) {
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
        return self::resolve('hibla-database', 'HIBLA_DB_CONFIG');
    }

    /**
     * Safely retrieve the migrations configuration.
     *
     * @return array<string, mixed>|null
     */
    public static function getMigrationsConfig(): ?array
    {
        return self::resolve('hibla-migrations', 'HIBLA_MIGRATIONS_CONFIG');
    }
}