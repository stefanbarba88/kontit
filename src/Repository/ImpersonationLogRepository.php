<?php

namespace App\Repository;

use App\Entity\ImpersonationLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImpersonationLog>
 */
class ImpersonationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImpersonationLog::class);
    }

    /** @return list<ImpersonationLog> najnovije prvo */
    public function findForTarget(User $target, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')->addSelect('a')->join('l.actor', 'a')
            ->where('l.target = :t')->setParameter('t', $target)
            ->orderBy('l.startedAt', 'DESC')->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return list<ImpersonationLog> najnovije prvo, za superadmina */
    public function findRecent(int $limit = 200): array
    {
        return $this->createQueryBuilder('l')->addSelect('a', 't')->join('l.actor', 'a')->join('l.target', 't')
            ->orderBy('l.startedAt', 'DESC')->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return list<ImpersonationLog> otvoreni zapisi kojima je poslednja aktivnost starija od praga */
    public function findStale(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.endedAt IS NULL AND l.lastActivityAt < :b')->setParameter('b', $before)
            ->getQuery()->getResult();
    }
}
