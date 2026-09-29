<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<ImportJob> */
final class ImportJobRepository extends EntityRepository
{
    public function findById(int $id): ?ImportJob { return $this->find($id); }
}
