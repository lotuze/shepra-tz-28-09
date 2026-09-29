<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\Product;
use App\Import\DecimalNormalizer;
use App\Import\ProductImportService;
use App\Import\ProductRow;
use App\Import\ProductRowValidator;
use App\Repository\ProductRepository;
use App\Tests\TestKernel;
use PHPUnit\Framework\TestCase;

final class ProductImportServiceTest extends TestCase
{
    public function testCreateThenUpsertReplacesRelationsWithoutDuplicate(): void
    {
        /** @var ProductRepository $repository */
        $repository = TestKernel::$entityManager->getRepository(Product::class);
        $service = new ProductImportService(TestKernel::$entityManager, $repository, new ProductRowValidator(new DecimalNormalizer()), new FakeImageDownloader(), dirname(__DIR__, 2) . '/storage');
        $service->import(new ProductRow('UPSERT-1', 'First', 'First description', '100.00', '50.00', '50.00', ['Цвет' => 'Красный'], ['https://example.test/one.jpg']));
        $service->import(new ProductRow('UPSERT-1', 'Second', 'Second description', '120.00', '60.00', '50.00', ['Размер' => 'M'], ['https://example.test/two.jpg']));
        TestKernel::$entityManager->clear();
        $product = $repository->findWithRelations((int) $repository->findByExternalCode('UPSERT-1')?->getId());
        self::assertSame('Second', $product?->getName());
        self::assertCount(1, $product->getAttributes());
        self::assertSame('Размер', $product->getAttributes()->first()->getKey());
        self::assertCount(1, $product->getImages());
        self::assertSame('https://example.test/two.jpg', $product->getImages()->first()->getUrl());
        self::assertSame(1, $repository->count(['externalCode' => 'UPSERT-1']));
        TestKernel::$entityManager->remove($product);
        TestKernel::$entityManager->flush();
    }
}
