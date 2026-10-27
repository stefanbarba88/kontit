<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Timeline jednog entiteta, najnovije prvo.
     *
     * @return list<ActivityLog>
     */
    public function findForEntity(string $type, int $id, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('u')
            ->leftJoin('l.user', 'u')
            ->andWhere('l.entityType = :type AND l.entityId = :id')
            ->setParameter('type', $type)
            ->setParameter('id', $id)
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Aktivnost korisnika: ono što je on radio i ono što se dešavalo njemu (npr. promena tima).
     *
     * @return list<ActivityLog>
     */
    public function findForUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('u')
            ->leftJoin('l.user', 'u')
            ->andWhere('l.user = :user OR (l.entityType = :userType AND l.entityId = :id)')
            ->setParameter('user', $user)
            ->setParameter('userType', ActivityLog::USER)
            ->setParameter('id', $user->getId())
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
