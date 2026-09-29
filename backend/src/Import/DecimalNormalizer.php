<?php

declare(strict_types=1);

namespace App\Import;

final class DecimalNormalizer
{
    public function normalize(mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('Value is not a decimal.');
        }
        $normalized = str_replace(["\u{00A0}", ' '], '', trim((string) $value));
        $normalized = str_replace(',', '.', $normalized);
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $normalized) !== 1) {
            throw new \InvalidArgumentException('Invalid decimal value.');
        }
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = ltrim($whole, '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($fraction, 2, '0');
    }

    public function cents(string $value): int
    {
        [$whole, $fraction] = explode('.', $value);
        return ((int) ($whole === '' ? '0' : $whole) * 100) + (int) $fraction;
    }
}
