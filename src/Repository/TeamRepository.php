<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->addSelect('l')
            ->join('t.leader', 'l')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy('t.name', 'ASC');
    }

    public function findActiveLedBy(User $user): ?Team
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.leader = :user')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Koliko aktivnih timova vodi korisnik (opciono bez jednog tima).
     */
    public function countActiveLedBy(User $user, ?Team $except = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.leader = :user')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('user', $user);

        if (null !== $except) {
            $qb->andWhere('t != :except')->setParameter('except', $except);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
