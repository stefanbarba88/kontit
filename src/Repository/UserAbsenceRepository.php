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
    /** Član tima koji $me vodi i na nivou je radnika (šef ne vidi odsustva člana koji je šef ili admin) */
    private const LEADS = 'EXISTS (SELECT 1 FROM App\Entity\TeamMember m JOIN m.team t JOIN m.user mu WHERE m.user = a.user AND m.leftAt IS NULL AND t.deletedAt IS NULL AND t.leader = :me AND '.self::WORKER.')';
    private const WORKER = "(mu.roles NOT LIKE '%ROLE_SEF%' AND mu.roles NOT LIKE '%ROLE_ADMIN%' AND mu.roles NOT LIKE '%ROLE_SUPERADMIN%')";

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
            ->addSelect('u', 'ty')
            ->join('a.user', 'u')
            ->join('a.type', 'ty')
            ->orderBy('a.startDate', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        $this->scope($qb, $me, $admin, $leader);

        if ($onlyCurrent) {
            $qb->andWhere('a.endDate >= :today AND a.status IN (:live)')
                ->setParameter('today', new \DateTimeImmutable('today'))
                ->setParameter('live', AbsenceStatus::blocking());
        }

        return $qb;
    }

    /**
     * Odobreno (i automatski) odsustvo korisnika koje pokriva $date, ili null.
     */
    public function findApprovedCovering(User $user, \DateTimeImmutable $date): ?UserAbsence
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user AND a.status IN (:ok) AND a.startDate <= :d AND a.endDate >= :d')
            ->setParameter('user', $user)
            ->setParameter('ok', AbsenceStatus::approved())
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
            ->setParameter('live', AbsenceStatus::blocking())
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
            ->addSelect('u', 'ty')
            ->join('a.user', 'u')
            ->join('a.type', 'ty')
            ->andWhere('a.status = :pending')
            ->setParameter('pending', AbsenceStatus::NA_ODOBRENJU)
            ->orderBy('a.approvalDeadline', 'ASC')
            ->addOrderBy('a.startDate', 'ASC');

        if (!$admin) {
            $qb->andWhere('a.user <> :me AND '.self::LEADS)->setParameter('me', $me);
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
            ->andWhere('a.user IN (:users) AND a.status IN (:ok) AND a.startDate <= :d AND a.endDate >= :d')
            ->setParameter('users', $users)
            ->setParameter('ok', AbsenceStatus::approved())
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

    /**
     * Iskorišćeni dani godišnjeg odmora u godini (po datumu početka), opciono samo do $until ili samo zahtevi na čekanju.
     */
    public function sumLeaveDays(User $user, int $year, ?\DateTimeImmutable $until = null, bool $pendingOnly = false): float
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.workingDays), 0)')
            ->join('a.type', 'ty')
            ->andWhere('a.user = :user AND ty.countsAgainstLeave = true AND a.startDate BETWEEN :from AND :to AND a.status IN (:st)')
            ->setParameter('user', $user)
            ->setParameter('from', new \DateTimeImmutable($year.'-01-01'))
            ->setParameter('to', $until ?? new \DateTimeImmutable($year.'-12-31'))
            ->setParameter('st', $pendingOnly ? [AbsenceStatus::NA_ODOBRENJU] : AbsenceStatus::approved());

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Dani jedne vrste u godini (za ograničenje maxDaysPerYear), odobreni i na čekanju.
     */
    public function sumTypeDays(User $user, \App\Entity\AbsenceType $type, int $year, ?UserAbsence $ignore = null): float
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.workingDays), 0)')
            ->andWhere('a.user = :user AND a.type = :type AND a.startDate BETWEEN :from AND :to AND a.status IN (:st)')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('from', new \DateTimeImmutable($year.'-01-01'))
            ->setParameter('to', new \DateTimeImmutable($year.'-12-31'))
            ->setParameter('st', AbsenceStatus::blocking());
        if (null !== $ignore?->getId()) {
            $qb->andWhere('a.id <> :self')->setParameter('self', $ignore->getId());
        }

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Odsustva (odobrena i na čekanju) koja seku period, za datog korisnika ili listu korisnika.
     *
     * @param list<User> $users
     *
     * @return list<UserAbsence>
     */
    public function findInPeriod(array $users, \DateTimeImmutable $from, \DateTimeImmutable $to, bool $includePending = true): array
    {
        if ([] === $users) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->addSelect('u', 'ty')
            ->join('a.user', 'u')
            ->join('a.type', 'ty')
            ->andWhere('a.user IN (:users) AND a.status IN (:st) AND a.startDate <= :to AND a.endDate >= :from')
            ->setParameter('users', $users)
            ->setParameter('st', $includePending ? AbsenceStatus::blocking() : AbsenceStatus::approved())
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Zbir radnih dana po korisniku i vrsti za godinu (evidencija), samo odobrena.
     *
     * @param list<User> $users
     *
     * @return array<int, array<int, float>> id korisnika => [id vrste => dani]
     */
    public function sumsByUserAndType(array $users, int $year): array
    {
        if ([] === $users) {
            return [];
        }
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.user) uid', 'IDENTITY(a.type) tid', 'SUM(a.workingDays) days')
            ->andWhere('a.user IN (:users) AND a.status IN (:ok) AND a.startDate BETWEEN :from AND :to')
            ->setParameter('users', $users)
            ->setParameter('ok', AbsenceStatus::approved())
            ->setParameter('from', new \DateTimeImmutable($year.'-01-01'))
            ->setParameter('to', new \DateTimeImmutable($year.'-12-31'))
            ->groupBy('uid', 'tid')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['uid']][(int) $row['tid']] = (float) $row['days'];
        }

        return $out;
    }

    /**
     * Zahtevi na odobrenju kojima je istekao rok (SPEC 8m), ili ističe pre $before.
     *
     * @return list<UserAbsence>
     */
    public function findPendingWithDeadlineBefore(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :pending AND a.approvalDeadline IS NOT NULL AND a.approvalDeadline <= :before')
            ->setParameter('pending', AbsenceStatus::NA_ODOBRENJU)
            ->setParameter('before', $before)
            ->orderBy('a.approvalDeadline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function scope(QueryBuilder $qb, User $me, bool $admin, bool $leader): void
    {
        if ($admin) {
            return;
        }
        $qb->andWhere($leader ? 'a.user = :me OR '.self::LEADS : 'a.user = :me')->setParameter('me', $me);
    }
}
