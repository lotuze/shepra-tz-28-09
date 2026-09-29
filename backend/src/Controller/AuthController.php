<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(private readonly UserRepository $users, private readonly JwtService $jwt)
    {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }
        if (!is_array($body) || !isset($body['email'], $body['password']) || !is_string($body['email']) || !is_string($body['password']) || trim($body['email']) === '' || $body['password'] === '') {
            return $this->json($response, ['error' => 'Email and password are required.'], 400);
        }

        $user = $this->users->findByEmail(User::normalizeEmail($body['email']));
        if ($user === null || !password_verify($body['password'], $user->getPasswordHash())) {
            return $this->json($response, ['error' => 'Invalid email or password.'], 401);
        }

        $issued = $this->jwt->issue($user);

        return $this->json($response, [
            'token' => $issued['token'],
            'user' => ['id' => $user->getId(), 'email' => $user->getEmail()],
            'expiresAt' => $issued['expiresAt']->format(DATE_ATOM),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
