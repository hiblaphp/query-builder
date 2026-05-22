<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console\Traits;

use InvalidArgumentException;
use Rcalicdan\ConfigLoader\Config;

trait ValidateConnection
{
    /**
     * Validate that a connection exists in the configuration.
     *
     * @throws InvalidArgumentException
     */
    private function validateConnection(?string $connection): void
    {
        if ($connection === null) {
            return;
        }

        $availableConnections = $this->getAvailableConnections();

        if ($availableConnections === []) {
            throw new InvalidArgumentException(
                'No database connections configured in pdo-query-builder config file'
            );
        }

        if (! \in_array($connection, $availableConnections, true)) {
            $availableList = implode(', ', $availableConnections);

            throw new InvalidArgumentException(
                "Connection '{$connection}' is not defined in config. " .
                    "Available connections: {$availableList}"
            );
        }
    }

    /**
     * Get all available connection names from config.
     *
     * @return list<string>
     */
    private function getAvailableConnections(): array
    {
        try {
            $dbConfig = Config::get('async-database');

            if (! \is_array($dbConfig)) {
                return [];
            }

            $connections = $dbConfig['connections'] ?? [];

            if (! \is_array($connections)) {
                return [];
            }

            return array_keys($connections);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
