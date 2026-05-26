<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Traits;

trait ExtractsConfigValues
{
    /**
     * Safely extract a string value from a mixed config array.
     *
     * Returns {@see $default} when the key is absent or its value is not a string.
     *
     * @param array<string, mixed> $config
     */
    private function extractString(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return \is_string($value) ? $value : $default;
    }

    /**
     * Safely extract and stringify the port from a mixed config array.
     *
     * Accepts the port value as either an integer or a string; falls back to
     * {@see $default} when the value is absent or of an unexpected type.
     *
     * @param array<string, mixed> $config
     */
    private function extractPort(array $config, string $default = '3306'): string
    {
        $value = $config['port'] ?? null;

        if (\is_int($value)) {
            return (string) $value;
        }

        return \is_string($value) ? $value : $default;
    }

    /**
     * Build an {@see array<string, string>} environment map for a password variable.
     *
     * Returns an empty array when no valid string password is configured,
     * ensuring the return type is always {@see array<string, string>} rather
     * than the broader {@see array<string, mixed>} PHPStan would otherwise infer.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, string>
     */
    private function extractPasswordEnv(array $config, string $envKey): array
    {
        $password = $config['password'] ?? '';

        if (\is_string($password) && $password !== '') {
            return [$envKey => $password];
        }

        return [];
    }
}
