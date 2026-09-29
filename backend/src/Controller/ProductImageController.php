<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductImageRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductImageController
{
    public function __construct(
        private readonly ProductImageRepository $images,
        private readonly string $storagePath,
    ) {
    }

    /** @param array{id: string} $arguments */
    public function content(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        $image = $this->images->find((int) $arguments['id']);
        if ($image === null || $image->getPath() === null) {
            return $response->withStatus(404);
        }

        $storageRoot = realpath($this->storagePath);
        $file = $storageRoot === false ? false : realpath($storageRoot . DIRECTORY_SEPARATOR . $image->getPath());
        if ($file === false
            || !is_file($file)
            || !str_starts_with($file, $storageRoot . DIRECTORY_SEPARATOR)) {
            return $response->withStatus(404);
        }

        $contentType = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!is_string($contentType) || !str_starts_with($contentType, 'image/')) {
            return $response->withStatus(404);
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Length', (string) strlen($contents))
            ->withHeader('Cache-Control', 'public, max-age=86400, immutable');
    }
}
