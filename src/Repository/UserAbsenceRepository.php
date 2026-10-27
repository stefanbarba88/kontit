<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserAbsence;
use App\Enum\AbsenceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAbsence>
 */
class UserAbsenceRepository extends ServiceEntityRepository
{
    /** Odsustvo koje čeka odobrenje ili je odobreno blokira novo odsustvo u istom periodu */
    private const BLOCKING = [AbsenceStatus::NA_CEKANJU, AbsenceStatus::ODOBRENO];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAbsence::class);
    }

    /**
     * Lista odsustava koje $me sme da vidi: admin+ sva, šef svoje i članova svojih timova, ostali samo svoja.
     */
    public function createListQueryBuilder(User $me, bool $admin, bool $leader, bool $onlyCurrent): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('u')
            ->join('a.user', 'u')
            ->orderBy('a.startDate', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if (!$admin) {
            $visible = 'a.user = :me';
            if ($leader) {
                $visible .= ' OR EXISTS (SELECT 1 FROM App\Entity\TeamMember m JOIN m.team t '
                    .'WHERE m.user = a.user AND m.leftAt IS NULL AND t.deletedAt IS NULL AND t.leader = :me)';
            }
            $qb->andWhere($visible)->setParameter('me', $me);
        }

        if ($onlyCurrent) {
            $qb->andWhere('a.endDate >= :today AND a.status IN (:live)')
                ->setParameter('today', new \DateTimeImmutable('today'))
                ->setParameter('live', self::BLOCKING);
        }

        return $qb;
    }

    /**
     * Odobreno odsustvo korisnika koje pokriva $date, ili null.
     */
    public function findApprovedCovering(User $user, \DateTimeImmutable $date): ?UserAbsence
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user AND a.status = :ok AND a.startDate <= :d AND a.endDate >= :d')
            ->setParameter('user', $user)
            ->setParameter('ok', AbsenceStatus::ODOBRENO)
            ->setParameter('d', $date)
            ->orderBy('a.endDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Da li se period preklapa sa odsustvom koje čeka ili je odobreno ($ignore je odsustvo koje se menja).
     */
    public function hasOverlap(User $user, \DateTimeImmutable $start, \DateTimeImmutable $end, ?UserAbsence $ignore = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.user = :user AND a.status IN (:live) AND a.startDate <= :end AND a.endDate >= :start')
            ->setParameter('user', $user)
            ->setParameter('live', self::BLOCKING)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if (null !== $ignore?->getId()) {
            $qb->andWhere('a.id <> :self')->setParameter('self', $ignore->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Koliko odsustava čeka odobrenje koje $me može da odobri (admin+ sva, šef članova svojih timova osim svojih).
     */
    public function countPendingFor(User $me, bool $admin): int
    {
        return (int) $this->createPendingQueryBuilder($me, $admin)->select('COUNT(a.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
    }

    public function createPendingQueryBuilder(User $me, bool $admin): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('u')
            ->join('a.user', 'u')
            ->andWhere('a.status = :pending AND a.endDate >= :today')
            ->setParameter('pending', AbsenceStatus::NA_CEKANJU)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('a.startDate', 'ASC');

        if (!$admin) {
            $qb->andWhere('a.user <> :me AND EXISTS (SELECT 1 FROM App\Entity\TeamMember m JOIN m.team t '
                .'WHERE m.user = a.user AND m.leftAt IS NULL AND t.deletedAt IS NULL AND t.leader = :me)')
                ->setParameter('me', $me);
        }

        return $qb;
    }

    /**
     * Korisnici koji su na dan $date odsutni (odobreno), po ID-u korisnika.
     *
     * @param list<User> $users
     *
     * @return array<int, UserAbsence>
     */
    public function findApprovedCoveringForUsers(array $users, \DateTimeImmutable $date): array
    {
        if ([] === $users) {
            return [];
        }

        $found = $this->createQueryBuilder('a')
            ->addSelect('u')
            ->join('a.user', 'u')
            ->andWhere('a.user IN (:users) AND a.status = :ok AND a.startDate <= :d AND a.endDate >= :d')
            ->setParameter('users', $users)
            ->setParameter('ok', AbsenceStatus::ODOBRENO)
            ->setParameter('d', $date)
            ->orderBy('a.endDate', 'ASC')
            ->getQuery()
            ->getResult();

        $byUser = [];
        foreach ($found as $absence) {
            $byUser[$absence->getUser()->getId()] = $absence;
        }

        return $byUser;
    }
}
