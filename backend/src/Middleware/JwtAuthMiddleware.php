<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Security\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly JwtService $jwt) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            return $this->unauthorized();
        }
        try {
            $user = $this->jwt->decode($matches[1]);
        } catch (\Throwable) {
            return $this->unauthorized();
        }

        return $handler->handle(
            $request->withAttribute('authenticatedUserId', $user['id'])
                ->withAttribute('authenticatedUserEmail', $user['email']),
        );
    }

    private function unauthorized(): ResponseInterface
    {
        $response = new Response(401);
        $response->getBody()->write(json_encode(['error' => 'Unauthorized.'], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
