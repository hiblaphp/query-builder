<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\States;

class MySQLSchemaState extends SchemaState
{
    public function dump(array $config, string $path, string $migrationsTable): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $user = $config['username'] ?? 'root';
        $db = $config['database'] ?? '';

        $env = isset($config['password']) && $config['password'] !== ''
            ? ['MYSQL_PWD' => $config['password']]
            : [];

        $this->executeCommandAndWriteToFile(
            ['mysqldump', '-u', $user, '-h', $host, '-P', $port, '--no-data', '--skip-comments', '--skip-routines', '--no-tablespaces', $db],
            $path,
            false,
            $env
        );

        $this->executeCommandAndWriteToFile(
            ['mysqldump', '-u', $user, '-h', $host, '-P', $port, '--no-create-info', '--skip-comments', '--no-tablespaces', $db, $migrationsTable],
            $path,
            true,
            $env
        );
    }

    public function load(array $config, string $path): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $user = $config['username'] ?? 'root';
        $db = $config['database'] ?? '';

        $env = isset($config['password']) && $config['password'] !== ''
            ? ['MYSQL_PWD' => $config['password']]
            : [];

        $this->executeCommandFromFile(
            ['mysql', '-u', $user, '-h', $host, '-P', $port, $db],
            $path,
            $env
        );
    }
}
