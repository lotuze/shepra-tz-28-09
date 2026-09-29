<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(private readonly string $secret, private readonly int $ttl)
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT_SECRET must contain at least 32 characters.');
        }
        if ($ttl <= 0) {
            throw new \InvalidArgumentException('JWT_TTL must be greater than zero.');
        }
    }

    /** @return array{token: string, expiresAt: DateTimeImmutable} */
    public function issue(User $user): array
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->ttl;
        $id = $user->getId() ?? throw new \LogicException('Cannot issue a token for a transient user.');
        $token = JWT::encode([
            'sub' => (string) $id,
            'email' => $user->getEmail(),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ], $this->secret, 'HS256');

        return ['token' => $token, 'expiresAt' => (new DateTimeImmutable())->setTimestamp($expiresAt)];
    }

    /** @return array{id: int, email: string} */
    public function decode(string $token): array
    {
        $payload = JWT::decode($token, new Key($this->secret, 'HS256'));
        if (!isset($payload->sub, $payload->email) || !is_string($payload->sub) || !is_string($payload->email) || filter_var($payload->sub, FILTER_VALIDATE_INT) === false) {
            throw new \UnexpectedValueException('JWT payload is invalid.');
        }

        return ['id' => (int) $payload->sub, 'email' => $payload->email];
    }
}
