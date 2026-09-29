<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\TestKernel;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthApiTest extends TestCase
{
    use AuthenticationTrait;

    public function testSuccessfulLoginDoesNotExposePasswordHash(): void
    {
        $response = TestKernel::app()->handle($this->jsonRequest('/api/auth/login', [
            'email' => ' ADMIN@EXAMPLE.COM ',
            'password' => 'ChangeMe123!',
        ]));
        $payload = $this->payload($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($payload['token']);
        self::assertSame('admin@example.com', $payload['user']['email']);
        self::assertArrayHasKey('expiresAt', $payload);
        self::assertStringNotContainsString('passwordHash', (string) $response->getBody());
        self::assertStringNotContainsString('$2y$', (string) $response->getBody());
    }

    /** @dataProvider invalidCredentials */
    public function testInvalidCredentialsHaveSameUnauthorizedResponse(string $email, string $password): void
    {
        $response = TestKernel::app()->handle($this->jsonRequest('/api/auth/login', compact('email', 'password')));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid email or password.'], $this->payload($response));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidCredentials(): iterable
    {
        yield 'wrong password' => ['admin@example.com', 'wrong'];
        yield 'unknown email' => ['unknown@example.com', 'ChangeMe123!'];
    }

    public function testInvalidBodyReturns400(): void
    {
        $factory = new ServerRequestFactory();
        $invalidJson = $factory->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $invalidJson->getBody()->write('{bad');
        self::assertSame(400, TestKernel::app()->handle($invalidJson)->getStatusCode());

        self::assertSame(400, TestKernel::app()->handle($this->jsonRequest('/api/auth/login', ['email' => 'admin@example.com']))->getStatusCode());
    }

    public function testProtectedEndpointRequiresValidToken(): void
    {
        $factory = new ServerRequestFactory();
        self::assertSame(401, TestKernel::app()->handle($factory->createServerRequest('GET', '/api/products'))->getStatusCode());
        self::assertSame(401, TestKernel::app()->handle($factory->createServerRequest('GET', '/api/products')->withHeader('Authorization', 'Bearer malformed'))->getStatusCode());

        $expired = JWT::encode([
            'sub' => '1',
            'email' => 'admin@example.com',
            'iat' => time() - 120,
            'exp' => time() - 60,
        ], 'test-only-jwt-secret-with-at-least-32-characters', 'HS256');
        self::assertSame(401, TestKernel::app()->handle($factory->createServerRequest('GET', '/api/products')->withHeader('Authorization', 'Bearer ' . $expired))->getStatusCode());

        self::assertSame(200, TestKernel::app()->handle($this->authorized($factory->createServerRequest('GET', '/api/products')))->getStatusCode());
    }

    public function testHealthAndLoginArePublic(): void
    {
        $factory = new ServerRequestFactory();
        self::assertSame(200, TestKernel::app()->handle($factory->createServerRequest('GET', '/api/health'))->getStatusCode());
        self::assertSame(401, TestKernel::app()->handle($this->jsonRequest('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong',
        ]))->getStatusCode());
    }

    /** @param array<string, string> $body */
    private function jsonRequest(string $uri, array $body): \Psr\Http\Message\ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $uri)->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        return $request;
    }

    /** @return array<string, mixed> */
    private function payload(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
