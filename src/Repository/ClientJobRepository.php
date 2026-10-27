<?php

namespace App\Repository;

use App\Entity\ClientJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientJob>
 */
class ClientJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientJob::class);
    }

    /**
     * Aktivni, neisključeni i odobreni poslovi aktivnih klijenata (kandidati za generisanje zadataka).
     *
     * @return list<ClientJob>
     */
    public function findGeneratable(): array
    {
        return $this->createQueryBuilder('j')
            ->addSelect('t', 'c')
            ->join('j.jobTemplate', 't')
            ->join('j.client', 'c')
            ->andWhere('j.isActive = true')
            ->andWhere('j.isApproved = true')
            ->andWhere('j.excludedAt IS NULL')
            ->andWhere('t.isActive = true')
            ->andWhere('c.isActive = true')
            ->getQuery()
            ->getResult();
    }
}
