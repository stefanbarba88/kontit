<?php

namespace App\Repository;

use App\Entity\JobCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobCategory>
 */
class JobCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobCategory::class);
    }

    /**
     * @return list<JobCategory>
     */
    public function findOrdered(bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.position', 'ASC')->addOrderBy('c.name', 'ASC');
        if ($activeOnly) {
            $qb->andWhere('c.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Broj poslova po kategoriji (id => broj).
     *
     * @return array<int, int>
     */
    public function jobCounts(): array
    {
        $rows = $this->getEntityManager()->createQuery('SELECT IDENTITY(j.category) id, COUNT(j.id) n FROM App\Entity\JobTemplate j GROUP BY j.category')->getArrayResult();

        return array_column($rows, 'n', 'id');
    }

    public function nextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('c')->select('MAX(c.position)')->getQuery()->getSingleScalarResult();
    }
}
