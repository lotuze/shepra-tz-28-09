<?php

declare(strict_types=1);

namespace App\Import;

final class ProductRowValidator
{
    public function __construct(private readonly DecimalNormalizer $decimals)
    {
    }

    public function validate(ProductRow $row): void
    {
        foreach (['externalCode' => $row->externalCode, 'name' => $row->name, 'description' => $row->description] as $field => $value) {
            if ($value === '') {
                throw new \InvalidArgumentException($field . ' is required.');
            }
        }
        if (mb_strlen($row->externalCode) > 255 || mb_strlen($row->name) > 255) {
            throw new \InvalidArgumentException('External code or name exceeds 255 characters.');
        }
        if ($this->decimals->cents($row->price) <= 0) {
            throw new \InvalidArgumentException('Sale price must be greater than zero.');
        }
        if (strlen($row->discount) > 6) {
            throw new \InvalidArgumentException('Discount does not fit decimal(5,2).');
        }
        foreach ($row->attributes as $key => $value) {
            if ($key === '' || mb_strlen($key) > 255) {
                throw new \InvalidArgumentException('Attribute key is invalid.');
            }
        }
    }
}
