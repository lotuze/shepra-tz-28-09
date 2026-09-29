<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ProductImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $products,
        private readonly ProductRowValidator $validator,
        private readonly ImageDownloaderInterface $images,
        private readonly string $storagePath,
    ) {
    }

    public function import(ProductRow $row): ProductImportResult
    {
        $this->validator->validate($row);
        $downloads = [];
        $warnings = [];
        foreach ($row->imageUrls as $url) {
            try {
                $downloads[$url] = $this->images->download($url);
            } catch (\Throwable $exception) {
                $downloads[$url] = null;
                $warnings[] = sprintf('Image %s: %s', $url, $exception->getMessage());
            }
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $product = $this->products->findByExternalCode($row->externalCode);
            if ($product === null) {
                $product = new Product($row->externalCode, $row->name, $row->description, $row->price, $row->discount);
            } else {
                $product->updateDetails($row->name, $row->description, $row->price, $row->discount);
                $product->clearAttributes();
                $product->clearImages();
                // Delete orphans before inserting replacement rows with the same unique keys.
                $this->entityManager->flush();
            }
            foreach ($row->attributes as $key => $value) {
                $product->addAttribute(new ProductAttribute($key, $value));
            }
            foreach ($row->imageUrls as $url) {
                $product->addImage(new ProductImage($url, $downloads[$url]?->path));
            }
            $this->entityManager->persist($product);
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
            foreach ($downloads as $download) {
                if ($download?->newlyCreated) {
                    $path = $this->storagePath . '/' . $download->path;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
            throw $exception;
        }
        return new ProductImportResult($warnings);
    }
}
