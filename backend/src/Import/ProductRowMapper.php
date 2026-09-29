<?php

declare(strict_types=1);

namespace App\Import;

final class ProductRowMapper
{
    private const PREFIX = 'Доп. поле: ';
    private const PACKAGING = 'Доп. поле: Ссылка на упаковку';
    private const PHOTOS = 'Доп. поле: Ссылки на фото';

    public function __construct(
        private readonly DecimalNormalizer $decimals,
        private readonly DiscountCalculator $discounts,
        private readonly ImageUrlExtractor $images,
    ) {}

    /** @param array<string, mixed> $data */
    public function map(array $data): ProductRow
    {
        $sale = $this->decimals->normalize($data['Цена: Цена продажи'] ?? null);
        $purchase = $this->decimals->normalize($data['Закупочная цена'] ?? null);
        $attributes = [];
        foreach ($data as $header => $value) {
            if (!str_starts_with($header, self::PREFIX) || in_array($header, [self::PACKAGING, self::PHOTOS], true)) { continue; }
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '') { $attributes[substr($header, strlen(self::PREFIX))] = $value; }
        }
        return new ProductRow(
            trim((string) ($data['Внешний код'] ?? '')),
            trim((string) ($data['Наименование'] ?? '')),
            trim((string) ($data['Описание'] ?? '')),
            $sale,
            $purchase,
            $this->discounts->calculate($sale, $purchase),
            $attributes,
            $this->images->extract($data[self::PACKAGING] ?? null, $data[self::PHOTOS] ?? null),
        );
    }
}
