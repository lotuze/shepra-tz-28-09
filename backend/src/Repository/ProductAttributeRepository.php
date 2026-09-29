<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductAttribute;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<ProductAttribute> */
final class ProductAttributeRepository extends EntityRepository
{
}
