<?php

declare(strict_types=1);

namespace App\Import;

final class DiscountCalculator
{
    public function __construct(private readonly DecimalNormalizer $decimals)
    {
    }

    public function calculate(string $salePrice, string $purchasePrice): string
    {
        $sale = $this->decimals->cents($salePrice);
        if ($sale <= 0) {
            throw new \InvalidArgumentException('Sale price must be greater than zero.');
        }
        $difference = $sale - $this->decimals->cents($purchasePrice);
        $numerator = $difference * 10000;
        $rounded = $numerator >= 0
            ? intdiv($numerator + intdiv($sale, 2), $sale)
            : -intdiv(abs($numerator) + intdiv($sale, 2), $sale);
        $absolute = abs($rounded);

        return sprintf(
            '%s%d.%02d',
            $rounded < 0 ? '-' : '',
            intdiv($absolute, 100),
            $absolute % 100,
        );
    }
}
