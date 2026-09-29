<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\DecimalNormalizer;
use App\Import\DiscountCalculator;
use App\Import\ImageUrlExtractor;
use App\Import\ProductRowMapper;
use App\Import\ProductRowValidator;
use PHPUnit\Framework\TestCase;

final class ImportMappingTest extends TestCase
{
    private DecimalNormalizer $decimals;
    private ProductRowMapper $mapper;

    protected function setUp(): void
    {
        $this->decimals = new DecimalNormalizer();
        $this->mapper = new ProductRowMapper($this->decimals, new DiscountCalculator($this->decimals), new ImageUrlExtractor());
    }

    public function testDecimalNormalization(): void
    {
        self::assertSame('1320.00', $this->decimals->normalize('1320,00'));
    }

    public function testDiscountCalculation(): void
    {
        $calculator = new DiscountCalculator($this->decimals);

        self::assertSame('33.33', $calculator->calculate('1320.00', '880.00'));
        self::assertSame('-0.50', $calculator->calculate('100.00', '100.50'));
        self::assertSame('-10.00', $calculator->calculate('100.00', '110.00'));
    }

    public function testZeroSalePriceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DiscountCalculator($this->decimals))->calculate('0.00', '0.00');
    }

    public function testMapsRequiredColumnsAttributesAndImages(): void
    {
        $row = $this->mapper->map([
            'Внешний код' => 'EXT-1', 'Наименование' => 'Товар', 'Описание' => 'Описание',
            'Цена: Цена продажи' => '1320,00', 'Закупочная цена' => '880,00',
            'Доп. поле: Цвет' => 'Красный', 'Доп. поле: Пустое' => '',
            'Доп. поле: Ссылка на упаковку' => 'https://example.test/a.jpg',
            'Доп. поле: Ссылки на фото' => 'https://example.test/a.jpg, https://example.test/b.jpg, ftp://example.test/c.jpg',
        ]);
        self::assertSame('EXT-1', $row->externalCode);
        self::assertSame(['Цвет' => 'Красный'], $row->attributes);
        self::assertSame(['https://example.test/a.jpg', 'https://example.test/b.jpg'], $row->imageUrls);
        self::assertArrayNotHasKey('Ссылка на упаковку', $row->attributes);
        self::assertArrayNotHasKey('Ссылки на фото', $row->attributes);
    }

    public function testInvalidRowIsRejected(): void
    {
        $row = $this->mapper->map([
            'Внешний код' => '', 'Наименование' => 'Товар', 'Описание' => 'Описание',
            'Цена: Цена продажи' => '100,00', 'Закупочная цена' => '50,00',
        ]);
        $this->expectException(\InvalidArgumentException::class);
        (new ProductRowValidator($this->decimals))->validate($row);
    }
}
