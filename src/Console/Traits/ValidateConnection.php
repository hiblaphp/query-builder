<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console\Traits;

use Hibla\QueryBuilder\Exceptions\DatabaseConfigurationException;
use Hibla\QueryBuilder\Exceptions\InvalidConnectionConfigException;
use Rcalicdan\ConfigLoader\Config;

trait ValidateConnection
{
    /**
     * Validate that a connection exists in the configuration.
     *
     * @throws DatabaseConfigurationException
     * @throws InvalidConnectionConfigException
     */
    private function validateConnection(?string $connection): void
    {
        if ($connection === null) {
            return;
        }

        $availableConnections = $this->getAvailableConnections();

        if ($availableConnections === []) {
            throw new DatabaseConfigurationException(
                'No database connections configured in migrations config file'
            );
        }

        if (! \in_array($connection, $availableConnections, true)) {
            $availableList = implode(', ', $availableConnections);

            throw new InvalidConnectionConfigException(
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
            $dbConfig = Config::loadFromRoot('hibla-database');

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
