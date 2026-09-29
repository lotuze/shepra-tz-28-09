<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Tests\TestKernel;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ProductImageApiTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function testServesExistingImage(): void
    {
        $relative = 'test-images/content.png';
        $absolute = dirname(__DIR__, 2) . '/storage/' . $relative;
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }
        file_put_contents($absolute, base64_decode(self::PNG, true));
        [$product, $image] = $this->persistImage($relative);

        $response = $this->request($image->getId());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame(base64_decode(self::PNG, true), (string) $response->getBody());
        self::assertStringContainsString('max-age=86400', $response->getHeaderLine('Cache-Control'));

        $this->removeProduct($product);
        unlink($absolute);
        rmdir(dirname($absolute));
    }

    public function testReturns404WhenPathIsNull(): void
    {
        [$product, $image] = $this->persistImage(null);

        self::assertSame(404, $this->request($image->getId())->getStatusCode());

        $this->removeProduct($product);
    }

    public function testReturns404WhenFileIsMissing(): void
    {
        [$product, $image] = $this->persistImage('test-images/missing.png');

        self::assertSame(404, $this->request($image->getId())->getStatusCode());

        $this->removeProduct($product);
    }

    public function testRejectsPathTraversal(): void
    {
        $outside = dirname(__DIR__, 2) . '/outside-test.png';
        file_put_contents($outside, base64_decode(self::PNG, true));
        [$product, $image] = $this->persistImage('../outside-test.png');

        self::assertSame(404, $this->request($image->getId())->getStatusCode());

        $this->removeProduct($product);
        unlink($outside);
    }

    /** @return array{Product, ProductImage} */
    private function persistImage(?string $path): array
    {
        $product = new Product('IMAGE-' . bin2hex(random_bytes(5)), 'Image product', 'Image test', '10.00', '0.00');
        $image = new ProductImage('https://example.test/' . bin2hex(random_bytes(5)) . '.png', $path);
        $product->addImage($image);
        TestKernel::$entityManager->persist($product);
        TestKernel::$entityManager->flush();

        return [$product, $image];
    }

    private function removeProduct(Product $product): void
    {
        TestKernel::$entityManager->remove($product);
        TestKernel::$entityManager->flush();
    }

    private function request(int $id): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/product-images/' . $id . '/content');

        return TestKernel::app()->handle($request);
    }
}
