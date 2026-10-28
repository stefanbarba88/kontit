<?php

namespace App\Repository;

use App\Entity\JobTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobTemplate>
 */
class JobTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobTemplate::class);
    }

    /**
     * Svi poslovi po redosledu kategorija, pa po nazivu.
     *
     * @return list<JobTemplate>
     */
    public function findForCatalog(bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('j')
            ->addSelect('c')
            ->join('j.category', 'c')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('j.name', 'ASC');
        if ($activeOnly) {
            $qb->andWhere('j.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }
}
