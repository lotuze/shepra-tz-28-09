<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class DatabaseConnectionParameters
{
    /** @return array{driver: string, host: string, port: int, user: string, password: string, dbname: string} */
    public static function fromEnvironment(): array
    {
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

        if (!is_string($databaseUrl) || $databaseUrl === '') {
            throw new \RuntimeException('DATABASE_URL environment variable is required.');
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false || !isset($parts['host'], $parts['user'], $parts['path'])) {
            throw new \RuntimeException('DATABASE_URL is invalid.');
        }

        return [
            'driver' => 'pdo_pgsql',
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 5432,
            'user' => rawurldecode($parts['user']),
            'password' => rawurldecode($parts['pass'] ?? ''),
            'dbname' => ltrim($parts['path'], '/'),
        ];
    }
}
