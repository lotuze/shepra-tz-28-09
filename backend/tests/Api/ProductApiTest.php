<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\TestKernel;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ProductApiTest extends TestCase
{
    use AuthenticationTrait;
    public function testProductList(): void
    {
        $response = $this->request('/api/products?page=2&limit=10');
        $payload = $this->payload($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertCount(10, $payload['data']);
        self::assertSame(2, $payload['meta']['page']);
        self::assertSame(30, $payload['meta']['total']);
    }

    public function testProductCardContainsRelations(): void
    {
        $response = $this->request('/api/products/1');
        $payload = $this->payload($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $payload['attributes']);
        self::assertCount(1, $payload['images']);
    }

    public function testMissingProductReturns404(): void
    {
        $response = $this->request('/api/products/99999');
        self::assertSame(404, $response->getStatusCode());
    }

    public function testInvalidPriceRangeReturns400(): void
    {
        $response = $this->request('/api/products?minPrice=1500&maxPrice=500');
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('minPrice must not be greater than maxPrice.', $this->payload($response)['error']);
    }

    private function request(string $uri): \Psr\Http\Message\ResponseInterface
    {
        $request = $this->authorized((new ServerRequestFactory())->createServerRequest('GET', $uri));
        return TestKernel::app()->handle($request);
    }

    /** @return array<string, mixed> */
    private function payload(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
