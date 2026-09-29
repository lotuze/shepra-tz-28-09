<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductAttributeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeRepository::class)]
#[ORM\Table(name: 'product_attributes')]
#[ORM\Index(name: 'idx_product_attributes_product', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'uniq_product_attribute_key', columns: ['product_id', 'attribute_key'])]
class ProductAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attributes')]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(name: 'attribute_key', length: 255)]
    private string $key;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value;

    public function __construct(string $key, ?string $value = null)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function getId(): ?int { return $this->id; }
    public function getProduct(): ?Product { return $this->product; }
    public function getKey(): string { return $this->key; }
    public function getValue(): ?string { return $this->value; }

    public function setProduct(Product $product): void { $this->product = $product; }

    public function unsetProduct(Product $product): void
    {
        if ($this->product === $product) {
            $this->product = null;
        }
    }
}
