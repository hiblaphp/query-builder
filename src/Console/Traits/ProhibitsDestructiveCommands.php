<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console\Traits;

use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ProhibitsDestructiveCommands
{
    /**
     * Check if destructive commands are prohibited by configuration.
     *
     * @return bool True if prohibited (should abort), False if allowed.
     */
    private function isDestructiveCommandProhibited(SymfonyStyle $io): bool
    {
        try {
            $isSafeMode = Config::loadFromRoot('hibla-migrations', 'safe_mode', false);

            if ($isSafeMode === true) {
                $io->newLine();
                $io->error([
                    'COMMAND ABORTED: SAFE MODE IS ENABLED!',
                    'Destructive commands (fresh, reset, refresh) are prohibited in this environment.',
                    'To run this command, set DB_SAFE_MODE=false in your .env file.',
                ]);

                return true;
            }
        } catch (\Throwable $e) {
            // If the config file completely fails to load, it default to allowing the command
            // since the command will likely fail naturally later if the DB isn't configured.
        }

        return false;
    }
}
