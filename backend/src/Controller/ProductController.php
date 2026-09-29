<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $page = $this->boundedInteger($query['page'] ?? null, 1, 1, PHP_INT_MAX);
        $limit = $this->boundedInteger($query['limit'] ?? null, 20, 1, 100);

        try {
            $filters = $this->filters($query);
        } catch (\InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $total = $this->products->countFiltered($filters);
        $items = $this->products->findPage($page, $limit, $filters);

        return $this->json($response, [
            'data' => array_map($this->productData(...), $items),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $total === 0 ? 0 : (int) ceil($total / $limit),
            ],
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        $product = $this->products->findWithRelations((int) $arguments['id']);

        if ($product === null) {
            return $this->json($response, ['error' => 'Product not found.'], 404);
        }

        $data = $this->productData($product);
        $data['attributes'] = array_map(
            static fn ($attribute): array => ['key' => $attribute->getKey(), 'value' => $attribute->getValue()],
            $product->getAttributes()->toArray(),
        );
        $data['images'] = array_map(
            static fn ($image): array => [
                'id' => $image->getId(),
                'url' => $image->getUrl(),
                'path' => $image->getPath(),
            ],
            $product->getImages()->toArray(),
        );

        return $this->json($response, $data);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{name?: string, minPrice?: string, maxPrice?: string}
     */
    private function filters(array $query): array
    {
        $filters = [];
        $rawName = $query['name'] ?? '';
        $name = is_scalar($rawName) ? trim((string) $rawName) : '';

        if ($name !== '') {
            $filters['name'] = $name;
        }

        foreach (['minPrice', 'maxPrice'] as $field) {
            if (!isset($query[$field]) || $query[$field] === '') {
                continue;
            }

            if (!is_scalar($query[$field])) {
                throw new \InvalidArgumentException(sprintf('%s must be a decimal value.', $field));
            }

            $value = (string) $query[$field];
            if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
                throw new \InvalidArgumentException(sprintf('%s must be a non-negative decimal with up to 2 fraction digits.', $field));
            }

            $filters[$field] = $value;
        }

        if (isset($filters['minPrice'], $filters['maxPrice'])
            && $this->decimalCents($filters['minPrice']) > $this->decimalCents($filters['maxPrice'])) {
            throw new \InvalidArgumentException('minPrice must not be greater than maxPrice.');
        }

        return $filters;
    }

    private function boundedInteger(mixed $value, int $default, int $minimum, int $maximum): int
    {
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return max($minimum, min($maximum, (int) $value));
    }

    private function decimalCents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /** @return array{id: int|null, externalCode: string, name: string, description: string, price: string, discount: string} */
    private function productData(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'discount' => $product->getDiscount(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
