<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/** @extends EntityRepository<Product> */
final class ProductRepository extends EntityRepository
{
    public function findById(int $id): ?Product
    {
        return $this->find($id);
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->findOneBy(['externalCode' => $externalCode]);
    }

    public function findWithRelations(int $id): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.attributes', 'a')->addSelect('a')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array{name?: string, minPrice?: string, maxPrice?: string} $filters
     * @return list<Product>
     */
    public function findPage(int $page, int $limit, array $filters): array
    {
        return $this->filteredQuery($filters)
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @param array{name?: string, minPrice?: string, maxPrice?: string} $filters */
    public function countFiltered(array $filters): int
    {
        return (int) $this->filteredQuery($filters)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array{name?: string, minPrice?: string, maxPrice?: string} $filters */
    private function filteredQuery(array $filters): QueryBuilder
    {
        $query = $this->createQueryBuilder('p');

        if (isset($filters['name'])) {
            $query
                ->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . mb_strtolower($filters['name']) . '%');
        }

        if (isset($filters['minPrice'])) {
            $query->andWhere('p.price >= :minPrice')->setParameter('minPrice', $filters['minPrice']);
        }

        if (isset($filters['maxPrice'])) {
            $query->andWhere('p.price <= :maxPrice')->setParameter('maxPrice', $filters['maxPrice']);
        }

        return $query;
    }
}
