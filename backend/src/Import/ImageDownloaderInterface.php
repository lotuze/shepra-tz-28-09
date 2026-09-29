<?php

declare(strict_types=1);

namespace App\Import;

interface ImageDownloaderInterface
{
    public function download(string $url): ImageDownloadResult;
}
