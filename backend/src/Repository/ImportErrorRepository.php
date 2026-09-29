<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportError;
use App\Entity\ImportJob;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<ImportError> */
final class ImportErrorRepository extends EntityRepository
{
    /** @return list<ImportError> */
    public function firstForJob(ImportJob $job, int $limit = 100): array
    {
        return $this->findBy(['importJob' => $job], ['id' => 'ASC'], $limit);
    }

    public function countForJob(ImportJob $job): int
    {
        return $this->count(['importJob' => $job]);
    }

    public function deleteForJob(ImportJob $job): int
    {
        return $this->createQueryBuilder('error')
            ->delete()
            ->where('error.importJob = :job')
            ->setParameter('job', $job)
            ->getQuery()
            ->execute();
    }
}
