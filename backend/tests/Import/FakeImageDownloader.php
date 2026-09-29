<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\ImageDownloaderInterface;
use App\Import\ImageDownloadResult;

final class FakeImageDownloader implements ImageDownloaderInterface
{
    public function __construct(private readonly bool $fail = false)
    {
    }

    public function download(string $url): ImageDownloadResult
    {
        if ($this->fail) {
            throw new \RuntimeException('Fake download failure.');
        }
        return new ImageDownloadResult('images/' . hash('sha256', $url) . '.img', false);
    }
}
