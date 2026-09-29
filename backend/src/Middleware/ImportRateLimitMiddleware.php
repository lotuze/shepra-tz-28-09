<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Security\ImportRateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class ImportRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ImportRateLimiter $limiter)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $request->getAttribute('authenticatedUserId');
        if (!is_int($userId)) {
            $response = new Response(401);
            $response->getBody()->write(json_encode(['error' => 'Unauthorized.'], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        }
        $result = $this->limiter->consume($userId);
        if (!$result['allowed']) {
            $response = new Response(429);
            $response->getBody()->write(json_encode(['error' => 'Import rate limit exceeded. Please try again later.'], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) $result['retryAfter']);
        }

        return $handler->handle($request);
    }
}
