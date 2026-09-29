<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductImage;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<ProductImage> */
final class ProductImageRepository extends EntityRepository
{
}
