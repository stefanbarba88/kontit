<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    /** Tabovi liste zadataka */
    public const TABS = ['today', 'week', 'overdue', 'review', 'approval', 'all'];

    /** Član tima koji $me vodi (aktivno članstvo, tim nije obrisan) je učesnik zadatka t */
    private const LEADS_ASSIGNEE = 'EXISTS (SELECT 1 FROM App\Entity\TaskAssignee la JOIN App\Entity\TeamMember lm WITH lm.user = la.user JOIN lm.team lt '
        .'WHERE la.task = t AND lm.leftAt IS NULL AND lt.deletedAt IS NULL AND lt.leader = :me)';

    private const IS_ASSIGNEE = 'EXISTS (SELECT 1 FROM App\Entity\TaskAssignee ma WHERE ma.task = t AND ma.user = :me)';

    /** Zadatak kolege koji je trenutno odsutan, a $me mu je backup na klijentu zadatka (zamena, SPEC 5) */
    private const IS_SUBSTITUTE = 'EXISTS (SELECT 1 FROM App\Entity\TaskAssignee sa JOIN t.client sc '
        .'WHERE sa.task = t AND sc.isActive = true AND sc.backupAccountant = :me AND sc.primaryAccountant = sa.user AND sa.user <> :me '
        .'AND EXISTS (SELECT 1 FROM App\Entity\UserAbsence ab WHERE ab.user = sa.user AND ab.status = :absApproved '
        .'AND ab.startDate <= :absToday AND ab.endDate >= :absToday))';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Lista zadataka za prikaz.
     * $scope 'mine': zadaci gde sam učesnik, kreator ili zamena odsutnog kolege. 'all' (admin+ i šef): svi koje korisnik sme da vidi.
     *
     * @param array{tab?: string, client?: ?Client, priority?: ?TaskPriority} $filters
     */
    public function createListQueryBuilder(User $me, bool $admin, bool $leader, string $scope, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('c')
            ->leftJoin('t.client', 'c')
            ->addSelect('CASE WHEN t.status IN (:closed) THEN 1 ELSE 0 END AS HIDDEN isClosed')
            ->setParameter('closed', [TaskStatus::ZAVRSENO, TaskStatus::OTKAZANO])
            ->orderBy('isClosed', 'ASC')
            ->addOrderBy('CASE WHEN t.dueDate IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('t.dueDate', 'ASC')
            ->addOrderBy('t.id', 'DESC');

        if ('all' === $scope && $admin) {
            // admin vidi sve
        } elseif ('all' === $scope && $leader) {
            $qb->andWhere('t.createdBy = :me OR '.self::IS_ASSIGNEE.' OR '.self::LEADS_ASSIGNEE.' OR '.self::IS_SUBSTITUTE)->setParameter('me', $me);
            $this->bindSubstitute($qb);
        } else {
            $qb->andWhere('t.createdBy = :me OR '.self::IS_ASSIGNEE.' OR '.self::IS_SUBSTITUTE)->setParameter('me', $me);
            $this->bindSubstitute($qb);
        }

        $today = new \DateTimeImmutable('today');
        $open = [TaskStatus::NOVO, TaskStatus::U_RADU, TaskStatus::CEKA_KLIJENTA, TaskStatus::NA_PROVERI];

        switch ($filters['tab'] ?? 'all') {
            case 'today':
                $qb->andWhere('t.dueDate = :today AND t.status IN (:open)')->setParameter('today', $today)->setParameter('open', $open);
                break;
            case 'week':
                $qb->andWhere('t.dueDate >= :today AND t.dueDate <= :weekEnd AND t.status IN (:open)')
                    ->setParameter('today', $today)->setParameter('weekEnd', $today->modify('sunday this week'))->setParameter('open', $open);
                break;
            case 'overdue':
                $qb->andWhere('t.dueDate < :today AND t.status IN (:open)')->setParameter('today', $today)->setParameter('open', $open);
                break;
            case 'review':
                $qb->andWhere('t.status = :review')->setParameter('review', TaskStatus::NA_PROVERI);
                break;
        }

        if (($filters['client'] ?? null) instanceof Client) {
            $qb->andWhere('t.client = :client')->setParameter('client', $filters['client']);
        }
        if (($filters['priority'] ?? null) instanceof TaskPriority) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $filters['priority']);
        }

        return $qb;
    }

    private function bindSubstitute(QueryBuilder $qb): void
    {
        $qb->setParameter('absApproved', \App\Enum\AbsenceStatus::ODOBRENO)->setParameter('absToday', new \DateTimeImmutable('today'));
    }

    /**
     * Zadaci na proveri koje $me sme da potvrdi: admin+ sve, šef one gde nije učesnik, a učesnik mu je u timu.
     */
    public function createAwaitingApprovalQueryBuilder(User $me, bool $admin): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('c')
            ->leftJoin('t.client', 'c')
            ->andWhere('t.status = :review')
            ->setParameter('review', TaskStatus::NA_PROVERI)
            ->orderBy('t.submittedAt', 'ASC');

        if (!$admin) {
            $qb->andWhere('NOT '.self::IS_ASSIGNEE)->andWhere(self::LEADS_ASSIGNEE)->setParameter('me', $me);
        }

        return $qb;
    }

    public function countAwaitingApproval(User $me, bool $admin): int
    {
        return (int) $this->createAwaitingApprovalQueryBuilder($me, $admin)
            ->select('COUNT(t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Koliko otvorenih zadataka korisniku kasni (rok prošao, njegov deo nije gotov). Za brojač u donjoj navigaciji.
     */
    public function countOverdueFor(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(DISTINCT t.id)')
            ->join('t.assignees', 'a')
            ->andWhere('a.user = :user AND a.status <> :done AND t.status IN (:workable) AND t.dueDate < :today')
            ->setParameter('user', $user)
            ->setParameter('done', \App\Enum\AssigneeStatus::ZAVRSIO)
            ->setParameter('workable', [TaskStatus::NOVO, TaskStatus::U_RADU, TaskStatus::CEKA_KLIJENTA])
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Koliko zadataka u kojima se još radi korisnik ima kao učesnik koji nije završio svoj deo.
     */
    public function countPendingFor(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(DISTINCT t.id)')
            ->join('t.assignees', 'a')
            ->andWhere('a.user = :user AND a.status <> :done AND t.status IN (:workable)')
            ->setParameter('user', $user)
            ->setParameter('done', \App\Enum\AssigneeStatus::ZAVRSIO)
            ->setParameter('workable', [TaskStatus::NOVO, TaskStatus::U_RADU, TaskStatus::CEKA_KLIJENTA])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Zadaci u kojima se još radi, sa rokom do (uključujući) $limit. Za dnevnu proveru rokova.
     *
     * @return list<Task>
     */
    public function findWorkableDueUntil(\DateTimeImmutable $limit): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('a')
            ->join('t.assignees', 'a')
            ->andWhere('t.status IN (:workable)')
            ->andWhere('t.dueDate IS NOT NULL AND t.dueDate <= :limit')
            ->setParameter('workable', [TaskStatus::NOVO, TaskStatus::U_RADU, TaskStatus::CEKA_KLIJENTA])
            ->setParameter('limit', $limit)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Otvoreni zadaci klijenta (za prikaz na stranici klijenta).
     *
     * @return list<Task>
     */
    public function findRecentByClient(Client $client, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.client = :client')
            ->setParameter('client', $client)
            ->orderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
