<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ProductImportResult
{
    /** @param list<string> $warnings */
    public function __construct(public array $warnings) {}
}
