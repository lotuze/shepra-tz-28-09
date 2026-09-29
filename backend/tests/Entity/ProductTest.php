<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testUpdateDetailsUpdatesImportableFields(): void
    {
        $product = $this->product();

        $product->updateDetails('Updated', 'Updated description', '125.50', '10.25');

        self::assertSame('TEST-1', $product->getExternalCode());
        self::assertSame('Updated', $product->getName());
        self::assertSame('Updated description', $product->getDescription());
        self::assertSame('125.50', $product->getPrice());
        self::assertSame('10.25', $product->getDiscount());
    }

    public function testAddAndRemoveAttributeKeepsBothSidesInSync(): void
    {
        $product = $this->product();
        $attribute = new ProductAttribute('Размер', '46(M)');

        $product->addAttribute($attribute);
        self::assertTrue($product->getAttributes()->contains($attribute));
        self::assertSame($product, $attribute->getProduct());

        $product->removeAttribute($attribute);
        self::assertFalse($product->getAttributes()->contains($attribute));
        self::assertNull($attribute->getProduct());
    }

    public function testAddAndRemoveImageKeepsBothSidesInSync(): void
    {
        $product = $this->product();
        $image = new ProductImage('https://example.test/image.jpg', 'image.jpg');

        $product->addImage($image);
        self::assertTrue($product->getImages()->contains($image));
        self::assertSame($product, $image->getProduct());

        $product->removeImage($image);
        self::assertFalse($product->getImages()->contains($image));
        self::assertNull($image->getProduct());
    }

    public function testClearAttributesKeepsBothSidesInSync(): void
    {
        $product = $this->product();
        $first = new ProductAttribute('Размер', '46(M)');
        $second = new ProductAttribute('Цвет', 'Чёрный');
        $product->addAttribute($first)->addAttribute($second);

        $product->clearAttributes();

        self::assertTrue($product->getAttributes()->isEmpty());
        self::assertNull($first->getProduct());
        self::assertNull($second->getProduct());
    }

    public function testClearImagesKeepsBothSidesInSync(): void
    {
        $product = $this->product();
        $first = new ProductImage('https://example.test/first.jpg', 'first.jpg');
        $second = new ProductImage('https://example.test/second.jpg', 'second.jpg');
        $product->addImage($first)->addImage($second);

        $product->clearImages();

        self::assertTrue($product->getImages()->isEmpty());
        self::assertNull($first->getProduct());
        self::assertNull($second->getProduct());
    }

    private function product(): Product
    {
        return new Product('TEST-1', 'Test', 'Test product', '100.00', '0.00');
    }
}
