<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
#[ORM\Table(name: 'product_images')]
#[ORM\Index(name: 'idx_product_images_product', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'uniq_product_image_url', columns: ['product_id', 'url'])]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'text')]
    private string $url;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $path;

    public function __construct(string $url, ?string $path = null)
    {
        $this->url = $url;
        $this->path = $path;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getProduct(): ?Product
    {
        return $this->product;
    }
    public function getUrl(): string
    {
        return $this->url;
    }
    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function unsetProduct(Product $product): void
    {
        if ($this->product === $product) {
            $this->product = null;
        }
    }
}
