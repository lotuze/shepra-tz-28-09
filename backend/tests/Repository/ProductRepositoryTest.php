<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Tests\TestKernel;
use PHPUnit\Framework\TestCase;

final class ProductRepositoryTest extends TestCase
{
    private ProductRepository $repository;

    protected function setUp(): void
    {
        /** @var ProductRepository $repository */
        $repository = TestKernel::$entityManager->getRepository(Product::class);
        $this->repository = $repository;
    }

    public function testPaginationUsesIdDescending(): void
    {
        $products = $this->repository->findPage(2, 10, []);

        self::assertCount(10, $products);
        self::assertSame(20, $products[0]->getId());
        self::assertSame(30, $this->repository->countFiltered([]));
    }

    public function testNameFilterIsCaseInsensitiveAndPartial(): void
    {
        $products = $this->repository->findPage(1, 20, ['name' => 'БЕРМУДЫ']);

        self::assertCount(6, $products);
        self::assertSame(6, $this->repository->countFiltered(['name' => 'бермуды']));
    }

    public function testPriceRangeFilter(): void
    {
        $filters = ['minPrice' => '500.00', 'maxPrice' => '650.00'];
        $products = $this->repository->findPage(1, 20, $filters);

        self::assertCount(3, $products);
        self::assertSame(3, $this->repository->countFiltered($filters));
    }
}
