<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ReviewMode;
use App\Enum\Role;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Service\TaskFilter;
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
    public const SPECIAL_TABS = ['special_waiting', 'special_progress', 'special_done'];

    /** Član tima koji $me vodi (aktivno članstvo, tim nije obrisan) je učesnik zadatka t */
    private const LEADS_ASSIGNEE = 'EXISTS (SELECT 1 FROM App\Entity\TaskAssignee la JOIN la.user lu JOIN App\Entity\TeamMember lm WITH lm.user = la.user JOIN lm.team lt '
        .'WHERE la.task = t AND lm.leftAt IS NULL AND lt.deletedAt IS NULL AND lt.leader = :me '
        ."AND (lu.roles NOT LIKE '%ROLE_SEF%' AND lu.roles NOT LIKE '%ROLE_ADMIN%' AND lu.roles NOT LIKE '%ROLE_SUPERADMIN%'))";

    private const IS_ASSIGNEE = 'EXISTS (SELECT 1 FROM App\Entity\TaskAssignee ma WHERE ma.task = t AND ma.user = :me)';

    /** Zadatak kolege koji je trenutno odsutan, a $me mu je backup na klijentu zadatka (zamena, SPEC 5) */
    private const IS_SUBSTITUTE = 'EXISTS (SELECT 1 FROM App\Entity\TaskAssignee sa JOIN t.client sc '
        .'WHERE sa.task = t AND sc.isActive = true AND sc.backupAccountant = :me AND sc.primaryAccountant = sa.user AND sa.user <> :me '
        .'AND EXISTS (SELECT 1 FROM App\Entity\UserAbsence ab WHERE ab.user = sa.user AND ab.status IN (:absApproved) '
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

    /**
     * JEDINI upit liste zadataka (/tasks, tab klijenta, tab korisnika): lista, brojači, izvoz i opcije filtera.
     * Vidljivost (kreator, učesnik, šef tima, zamena, admin) + svi filteri iz TaskFilter, klijent se učitava u istom upitu.
     */
    public function createFilteredQueryBuilder(TaskFilter $f, User $viewer): QueryBuilder
    {
        $qb = null !== $f->specialApprovalFor
            ? $this->createSpecialQueryBuilder($f)
            : ('approval' === $f->tab
            ? $this->createAwaitingApprovalQueryBuilder($viewer, $f->viewerAdmin)
            : $this->createListQueryBuilder($viewer, $f->viewerAdmin, $f->viewerLeader, $f->scope, ['tab' => $f->tab]));

        if (null !== $f->client) {
            $qb->andWhere('t.client = :fClient')->setParameter('fClient', $f->client);
        }
        if (null !== $f->priority) {
            $qb->andWhere('t.priority = :fPriority')->setParameter('fPriority', $f->priority);
        }
        if (null !== $f->status) {
            $qb->andWhere('t.status = :fStatus')->setParameter('fStatus', $f->status);
        } elseif ('all' === $f->tab && !$f->showClosed) {
            // podrazumevano samo otvoreni; tabovi vezani za status ("Na proveri" i sl.) ignorišu prekidač
            $qb->andWhere('t.status IN (:fOpen)')->setParameter('fOpen', self::openStatuses());
        }
        if (null !== $f->team) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\TaskAssignee fa WHERE fa.task = t AND (fa.user = :fTeamLeader OR EXISTS ('
                .'SELECT 1 FROM App\Entity\TeamMember fm WHERE fm.user = fa.user AND fm.team = :fTeam AND fm.leftAt IS NULL)))')
                ->setParameter('fTeam', $f->team)
                ->setParameter('fTeamLeader', $f->team->getLeader());
        }
        if (null !== $f->assignee) {
            $isAssignee = 'EXISTS (SELECT 1 FROM App\Entity\TaskAssignee fu WHERE fu.task = t AND fu.user = :fUser)';
            $qb->andWhere(match ($f->role) {
                'created' => 't.createdBy = :fUser',
                'both' => $isAssignee.' OR t.createdBy = :fUser',
                default => $isAssignee,
            })->setParameter('fUser', $f->assignee);
        }
        if (null !== $f->from) {
            // Potvrđeno: period se odnosi na datum potvrde, inače na rok
            $field = 'special_done' === $f->tab ? 't.approvedAt' : 't.dueDate';
            $qb->andWhere($field.' BETWEEN :fFrom AND :fTo')->setParameter('fFrom', $f->from)->setParameter('fTo', ('special_done' === $f->tab ? ($f->to ?? $f->from)->setTime(23, 59, 59) : ($f->to ?? $f->from)));
        }
        if ('' !== $f->q) {
            // title_search je već normalizovan (TextNormalizer), pa isti izraz traži bez razlike u kvačicama i velikim slovima
            $qb->andWhere('t.titleSearch LIKE :fQ')->setParameter('fQ', '%'.addcslashes($f->q, '%_\\').'%');
        }
        if ($f->noJob) {
            $qb->andWhere('t.clientJob IS NULL');
        } elseif (null !== $f->job) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\ClientJob fj WHERE fj = t.clientJob AND fj.jobTemplate = :fJob)')->setParameter('fJob', $f->job);
        }
        if ('auto' === $f->origin || 'manual' === $f->origin) {
            $qb->andWhere('t.generatedAutomatically = :fAuto')->setParameter('fAuto', 'auto' === $f->origin);
        }

        return $qb;
    }

    /** Posle zamene select-a i brisanja sortiranja parametar :closed (iz sortiranja) više nije u upitu, pa se ne sme slati */
    private function withoutSortParameters(QueryBuilder $qb): QueryBuilder
    {
        foreach ($qb->getParameters() as $parameter) {
            if ('closed' === $parameter->getName()) {
                $qb->getParameters()->removeElement($parameter);
            }
        }

        return $qb;
    }

    /** @return list<TaskStatus> */
    private static function openStatuses(): array
    {
        return array_values(array_filter(TaskStatus::cases(), static fn (TaskStatus $s) => $s->isOpen()));
    }

    /**
     * Učesnici zadataka koje $viewer vidi u datom opsegu (opcije filtera "Zaduženi" na stranici klijenta), svi statusi.
     *
     * @return list<User>
     */
    public function findAssigneesOf(TaskFilter $f, User $viewer): array
    {
        $all = $f->with(['assignee' => null, 'status' => null, 'showClosed' => true, 'q' => '', 'job' => null, 'noJob' => false, 'origin' => 'all', 'from' => null, 'to' => null, 'priority' => null, 'team' => null, 'tab' => 'all']);
        $ids = $this->createFilteredQueryBuilder($all, $viewer)->select('t.id')->resetDQLPart('orderBy');
        $ids = $this->withoutSortParameters($ids)->getQuery()->getSingleColumnResult();
        if ([] === $ids) {
            return [];
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('EXISTS (SELECT 1 FROM App\Entity\TaskAssignee x WHERE x.user = u AND x.task IN (:ids))')
            ->setParameter('ids', $ids)
            ->orderBy('u.firstName')->addOrderBy('u.lastName')
            ->getQuery()->getResult();
    }

    /**
     * Brojači za zaglavlje taba (otvoreni, kasne, na proveri, završeni ovog meseca) nad istim opsegom, bez ostalih filtera.
     *
     * @return array{open: int, overdue: int, review: int, doneMonth: int}
     */
    public function countSummary(TaskFilter $f, User $viewer): array
    {
        $base = $f->with(['status' => null, 'showClosed' => true, 'q' => '', 'job' => null, 'noJob' => false, 'origin' => 'all', 'from' => null, 'to' => null, 'priority' => null, 'team' => null, 'tab' => 'all']);
        $row = $this->createFilteredQueryBuilder($base, $viewer)
            ->select('SUM(CASE WHEN t.status IN (:sOpen) THEN 1 ELSE 0 END) AS open_count')
            ->addSelect('SUM(CASE WHEN t.status IN (:sOpen) AND t.dueDate < :sToday THEN 1 ELSE 0 END) AS overdue_count')
            ->addSelect('SUM(CASE WHEN t.status = :sReview THEN 1 ELSE 0 END) AS review_count')
            ->addSelect('SUM(CASE WHEN t.status = :sDone AND t.updatedAt >= :sMonth THEN 1 ELSE 0 END) AS done_count')
            ->resetDQLPart('orderBy');
        $row = $this->withoutSortParameters($row)
            ->setParameter('sOpen', self::openStatuses())
            ->setParameter('sToday', new \DateTimeImmutable('today'))
            ->setParameter('sReview', TaskStatus::NA_PROVERI)
            ->setParameter('sDone', TaskStatus::ZAVRSENO)
            ->setParameter('sMonth', new \DateTimeImmutable('first day of this month 00:00'))
            ->getQuery()->getSingleResult();

        return ['open' => (int) $row['open_count'], 'overdue' => (int) $row['overdue_count'], 'review' => (int) $row['review_count'], 'doneMonth' => (int) $row['done_count']];
    }

    /**
     * Učitava učesnike (sa korisnicima) i stavke checkliste za zadatke jedne strane u jednom upitu (bez N+1 u listi i izvozu).
     * Zadaci su već učitani, pa ovo samo inicijalizuje njihove kolekcije.
     *
     * @param iterable<Task> $tasks
     */
    public function hydrateListRelations(iterable $tasks): void
    {
        $tasks = $tasks instanceof \Traversable ? iterator_to_array($tasks, false) : array_values((array) $tasks);
        if ([] === $tasks) {
            return;
        }
        $this->createQueryBuilder('t')
            ->select('t', 'a', 'u', 'ci')
            ->leftJoin('t.assignees', 'a')->leftJoin('a.user', 'u')->leftJoin('t.checklistItems', 'ci')
            ->where('t IN (:tasks)')->setParameter('tasks', $tasks)
            ->getQuery()->getResult();
    }

    private function bindSubstitute(QueryBuilder $qb): void
    {
        $qb->setParameter('absApproved', \App\Enum\AbsenceStatus::approved())->setParameter('absToday', new \DateTimeImmutable('today'));
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
            ->andWhere('t.reviewMode <> :special')
            ->setParameter('review', TaskStatus::NA_PROVERI)
            ->setParameter('special', ReviewMode::SPECIAL)
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

    /**
     * Periodi za koje zadaci već postoje, po poslu klijenta (jedan upit za ceo pregled generisanja).
     *
     * @param list<\App\Entity\ClientJob> $jobs
     *
     * @return array<int, array<string, true>> id posla => [ključ perioda => true]
     */
    public function findPeriodKeys(array $jobs): array
    {
        if ([] === $jobs) {
            return [];
        }
        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(t.clientJob) job', 't.periodKey')
            ->andWhere('t.clientJob IN (:jobs) AND t.periodKey IS NOT NULL')
            ->setParameter('jobs', $jobs)
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['job']][$row['periodKey']] = true;
        }

        return $out;
    }

    /**
     * Posebne potvrde (SPEC: Posebne potvrde). Samo zadaci sa reviewMode SPECIAL. Proveravač vidi zadatke gde je u snapshot-u;
     * superadmin i one kojima nijedan izabrani proveravač nije više aktivan admin, a sa prekidačem "all" sve.
     * Tabovi: special_waiting (NA_PROVERI, najstariji prvi), special_progress (još nisu poslati), special_done (potvrđeni).
     */
    public function createSpecialQueryBuilder(TaskFilter $f): QueryBuilder
    {
        $me = $f->specialApprovalFor;
        $qb = $this->createQueryBuilder('t')->addSelect('c')->leftJoin('t.client', 'c')
            ->andWhere('t.reviewMode = :sMode')->setParameter('sMode', ReviewMode::SPECIAL);

        if (!$f->specialAll) {
            $mine = ':sMe MEMBER OF t.requiredApprovers';
            if (Role::SUPERADMIN === $me?->getHighestRole()) {
                $mine .= ' OR NOT EXISTS (SELECT 1 FROM App\Entity\User sa WHERE sa MEMBER OF t.requiredApprovers AND sa.isActive = true AND (sa.roles LIKE :sAdmin OR sa.roles LIKE :sSuper))';
                $qb->setParameter('sAdmin', '%"'.Role::ADMIN->value.'"%')->setParameter('sSuper', '%"'.Role::SUPERADMIN->value.'"%');
            }
            $qb->andWhere($mine)->setParameter('sMe', $me);
        }

        switch ($f->tab) {
            case 'special_progress':
                $qb->andWhere('t.status IN (:sWork)')->setParameter('sWork', [TaskStatus::NOVO, TaskStatus::U_RADU, TaskStatus::CEKA_KLIJENTA])
                    ->orderBy('CASE WHEN t.dueDate IS NULL THEN 1 ELSE 0 END', 'ASC')->addOrderBy('t.dueDate', 'ASC')->addOrderBy('t.id', 'DESC');
                break;
            case 'special_done':
                $qb->andWhere('t.status = :sDone AND t.approvedBy IS NOT NULL')->setParameter('sDone', TaskStatus::ZAVRSENO);
                if (!$f->specialAll) {
                    $qb->andWhere('t.approvedBy = :sMe');
                }
                $qb->orderBy('t.approvedAt', 'DESC')->addOrderBy('t.id', 'DESC');
                break;
            default:
                $qb->andWhere('t.status = :sReview')->setParameter('sReview', TaskStatus::NA_PROVERI)->orderBy('t.submittedAt', 'ASC')->addOrderBy('t.id', 'ASC');
        }

        return $qb;
    }

    /** Broj posebnih potvrda koje čekaju $user (brojač u meniju i na dashboardu); isti upit kao tab "Čeka potvrdu". */
    public function countAwaitingSpecialApproval(User $user, bool $all): int
    {
        $f = new TaskFilter(viewerAdmin: true, viewerLeader: true, scope: 'all', tab: 'special_waiting', specialApprovalFor: $user, specialAll: $all);

        return (int) $this->createFilteredQueryBuilder($f, $user)->select('COUNT(t.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
    }

    /** Najstarije slanje na posebnu potvrdu među onima koji čekaju (za karticu na dashboardu) */
    public function oldestAwaitingSpecialApproval(User $user, bool $all): ?\DateTimeImmutable
    {
        $f = new TaskFilter(viewerAdmin: true, viewerLeader: true, scope: 'all', tab: 'special_waiting', specialApprovalFor: $user, specialAll: $all);
        $value = $this->createFilteredQueryBuilder($f, $user)->select('MIN(t.submittedAt)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        return null === $value ? null : new \DateTimeImmutable((string) $value);
    }}
