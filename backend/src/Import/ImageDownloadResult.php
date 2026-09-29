<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ImageDownloadResult
{
    public function __construct(public string $path, public bool $newlyCreated)
    {
    }
}
