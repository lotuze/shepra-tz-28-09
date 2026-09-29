<?php

declare(strict_types=1);

namespace App\Import;

final readonly class SpreadsheetData
{
    /** @param list<array{rowNumber: int, data: array<string, mixed>}> $rows */
    public function __construct(public array $rows)
    {
    }
}
