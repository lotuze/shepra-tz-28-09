<?php

declare(strict_types=1);

namespace App\Security;

use Doctrine\DBAL\Connection;

final class ImportRateLimiter
{
    public function __construct(private readonly Connection $connection, private readonly int $limit, private readonly int $windowSeconds)
    {
        if ($limit <= 0 || $windowSeconds <= 0) {
            throw new \InvalidArgumentException('Import rate limit settings must be greater than zero.');
        }
    }

    /** @return array{allowed: bool, retryAfter: int} */
    public function consume(int $userId): array
    {
        $result = $this->connection->fetchAssociative(<<<'SQL'
            INSERT INTO import_rate_limits (user_id, window_started_at, request_count)
            VALUES (:user_id, CURRENT_TIMESTAMP, 1)
            ON CONFLICT (user_id) DO UPDATE SET
                window_started_at = CASE
                    WHEN import_rate_limits.window_started_at <= CURRENT_TIMESTAMP - (:window_seconds * INTERVAL '1 second') THEN CURRENT_TIMESTAMP
                    ELSE import_rate_limits.window_started_at
                END,
                request_count = CASE
                    WHEN import_rate_limits.window_started_at <= CURRENT_TIMESTAMP - (:window_seconds * INTERVAL '1 second') THEN 1
                    ELSE import_rate_limits.request_count + 1
                END
            RETURNING request_count,
                LEAST(:window_seconds, GREATEST(1, CEIL(EXTRACT(EPOCH FROM (window_started_at + (:window_seconds * INTERVAL '1 second') - CURRENT_TIMESTAMP))))::INT) AS retry_after
            SQL, ['user_id' => $userId, 'window_seconds' => $this->windowSeconds]);
        if ($result === false) {
            throw new \RuntimeException('Rate limiter did not return a result.');
        }

        return ['allowed' => (int) $result['request_count'] <= $this->limit, 'retryAfter' => (int) $result['retry_after']];
    }
}
