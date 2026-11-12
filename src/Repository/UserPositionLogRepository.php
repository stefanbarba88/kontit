<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPositionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPositionLog>
 */
class UserPositionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPositionLog::class);
    }

    /**
     * @return list<UserPositionLog> najnovije prvo
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('l')->addSelect('b')->leftJoin('l.changedBy', 'b')
            ->where('l.user = :u')->setParameter('u', $user)
            ->orderBy('l.changedAt', 'DESC')->addOrderBy('l.id', 'DESC')
            ->getQuery()->getResult();
    }
}
