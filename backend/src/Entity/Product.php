<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'uniq_products_external_code', columns: ['external_code'])]
#[ORM\Index(name: 'idx_products_name', columns: ['name'])]
#[ORM\Index(name: 'idx_products_price', columns: ['price'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'external_code', length: 255)]
    private string $externalCode;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $price;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $discount;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttribute::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $images;

    public function __construct(
        string $externalCode,
        string $name,
        string $description,
        string $price,
        string $discount,
    ) {
        $this->externalCode = $externalCode;
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getExternalCode(): string
    {
        return $this->externalCode;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    public function getPrice(): string
    {
        return $this->price;
    }
    public function getDiscount(): string
    {
        return $this->discount;
    }
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function updateDetails(
        string $name,
        string $description,
        string $price,
        string $discount,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
    }

    public function addAttribute(ProductAttribute $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setProduct($this);
        }

        return $this;
    }

    public function removeAttribute(ProductAttribute $attribute): self
    {
        if ($this->attributes->removeElement($attribute)) {
            $attribute->unsetProduct($this);
        }

        return $this;
    }

    public function clearAttributes(): void
    {
        foreach ($this->attributes->toArray() as $attribute) {
            $this->removeAttribute($attribute);
        }
    }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        if ($this->images->removeElement($image)) {
            $image->unsetProduct($this);
        }

        return $this;
    }

    public function clearImages(): void
    {
        foreach ($this->images->toArray() as $image) {
            $this->removeImage($image);
        }
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
