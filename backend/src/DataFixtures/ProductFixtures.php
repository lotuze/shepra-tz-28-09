<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class ProductFixtures implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $names = ['Бермуды', 'Футболка', 'Куртка', 'Джинсы', 'Рубашка'];

        for ($number = 1; $number <= 30; ++$number) {
            $name = $names[($number - 1) % count($names)] . ' ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $price = number_format(350 + ($number * 75), 2, '.', '');
            $discount = number_format(($number % 6) * 5, 2, '.', '');
            $product = new Product(
                sprintf('SKU-%04d', $number),
                $name,
                sprintf('Детерминированное описание товара %d.', $number),
                $price,
                $discount,
            );

            if ($number <= 10) {
                $product->addAttribute(new ProductAttribute('Размер', 44 + ($number % 5) . '(M)'));
                $product->addAttribute(new ProductAttribute('Цвет', $number % 2 === 0 ? 'Синий' : 'Чёрный'));
                $product->addImage(new ProductImage(
                    sprintf('https://example.test/images/product-%d.jpg', $number),
                    sprintf('products/product-%d.jpg', $number),
                ));
            }

            $manager->persist($product);
        }

        $manager->flush();
    }
}
