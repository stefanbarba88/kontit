<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\JobCategory;
use App\Entity\JobTemplate;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\MatrixState;
use App\Enum\TaskStatus;
use App\Repository\ClientRepository;
use App\Repository\JobCategoryRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Matrica "Pregled poslova po klijentima" (SPEC 8r): redovi su klijenti koje korisnik vidi (ClientRepository::createVisibleQueryBuilder),
 * kolone su poslovi (JobTemplate) izabrane kategorije poslova (JobCategory), ćelija = zadaci tog klijenta iz tog posla sa rokom u izabranom mesecu.
 * Brojevi idu kroz TaskRepository::createFilteredQueryBuilder, pa važi ista vidljivost zadataka kao u listi (radnik vidi samo svoje zadatke).
 * Upiti po prikazu: klijenti, poslovi klijenata u kategoriji, jedan agregat (GROUP BY klijent, posao), brojač zakasnelih i spisak kategorija;
 * ne zavise od broja redova (nema N+1).
 */
class ClientJobMatrixService
{
    /** Najviše redova zadataka u prozoru ćelije; ostalo je "i još N zadataka" */
    public const CELL_LIMIT = 8;

    public function __construct(
        private readonly ClientRepository $clients,
        private readonly JobCategoryRepository $categories,
        private readonly TaskRepository $tasks,
        private readonly ClientService $clientService,
        private readonly TaskService $taskService,
        private readonly WorkingDayService $workingDays,
        private readonly TextNormalizer $normalizer,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%matrix.warning_days%')]
        private readonly int $warningDays,
    ) {
    }

    private ?\DateTimeImmutable $fixedToday = null;

    /** Samo za testove: zamrzava "danas" */
    public function setToday(?\DateTimeImmutable $today): void
    {
        $this->fixedToday = $today?->setTime(0, 0);
    }

    public function today(): \DateTimeImmutable
    {
        return $this->fixedToday ?? new \DateTimeImmutable('today');
    }

    /** @param array<string, mixed> $query */
    public function filters(array $query): MatrixFilters
    {
        return MatrixFilters::fromQuery($query, $this->today());
    }

    /** Poslednji dan "ističe uskoro": N radnih dana posle danas (petak → ponedeljak je 1. radni dan) */
    public function soonUntil(): \DateTimeImmutable
    {
        $day = $this->today();
        for ($i = 0; $i < $this->warningDays; ++$i) {
            $day = $this->workingDays->nextWorkingDay($day->modify('+1 day'));
        }

        return $day;
    }

    /** Jedino mesto gde se računa stanje ćelije (prioritet odozgo). Zadaci su već bez otkazanih; zakasnelo i uskoro broji samo otvorene. */
    public function cellState(int $total, int $done, int $overdue, int $soon): MatrixState
    {
        return match (true) {
            0 === $total => MatrixState::NONE,
            $overdue > 0 => MatrixState::DANGER,
            $done === $total => MatrixState::SUCCESS,
            $soon > 0 => MatrixState::WARNING,
            default => MatrixState::IN_PROGRESS,
        };
    }

    public function cell(int $total, int $done, int $overdue, int $soon): MatrixCell
    {
        $state = $this->cellState($total, $done, $overdue, $soon);
        $summary = 'Nema zadataka u ovom periodu';
        if ($total > 0) {
            $summary = sprintf('%d od %d gotovo', $done, $total);
            if ($overdue > 0) {
                $summary .= sprintf(', %d %s', $overdue, 1 === $overdue ? 'kasni' : 'kasne');
            }
            if ($soon > 0) {
                $summary .= sprintf(', %d %s u narednih %d radna dana', $soon, 1 === $soon ? 'ističe' : 'ističu', $this->warningDays);
            }
        }

        return new MatrixCell($total, $done, $overdue, $soon, $state, $summary);
    }

    public function build(MatrixFilters $f, User $me, int $page = 1, int $perPage = 15): MatrixView
    {
        $canAll = $this->clientService->canAssignAnyone($me);
        /** @var list<Client> $visible */
        $visible = $this->clients->createVisibleQueryBuilder($me, $canAll, null, 'active')->getQuery()->getResult();

        $people = [];
        foreach ($visible as $client) {
            foreach ([$client->getPrimaryAccountant(), $client->getBackupAccountant()] as $person) {
                if (null !== $person) {
                    $people[$person->getId()] = $person;
                }
            }
        }
        usort($people, static fn (User $a, User $b) => strcmp($a->getFullName(), $b->getFullName()));

        // pretraga i zaposleni u PHP-u: TextNormalizer (bez kvačica, đ = dj) važi i za klijente, bez nove kolone
        $needle = $this->normalizer->normalize($f->q);
        $candidates = array_values(array_filter($visible, function (Client $c) use ($f, $needle): bool {
            if (null !== $f->userId && $c->getPrimaryAccountant()?->getId() !== $f->userId && $c->getBackupAccountant()?->getId() !== $f->userId) {
                return false;
            }

            return '' === $needle || str_contains($this->normalizer->normalize($c->getShortName().' '.$c->getName().' '.$c->getPib()), $needle);
        }));
        $ids = array_map(static fn (Client $c) => (int) $c->getId(), $candidates);

        // izabrana kategorija: neispravna, nepostojeća ili neaktivna = prva aktivna po redosledu
        $allCategories = $this->categories->findOrdered(true);
        $category = $this->resolveCategory($f->categoryId, $allCategories);
        $f = $f->with(['categoryId' => $category?->getId()]);

        // poslovi kategorije koje klijenti zaista imaju (aktivan, odobren, neisključen ClientJob); kolone ne zavise od pretrage
        $jobs = [];
        $has = [];
        if (null !== $category && [] !== $visible) {
            $found = $this->em->createQueryBuilder()->select('IDENTITY(cj.client) AS cid', 'IDENTITY(cj.jobTemplate) AS jid')
                ->from(ClientJob::class, 'cj')->join('cj.jobTemplate', 'jt')
                ->where('jt.category = :cat AND jt.isActive = true AND cj.isActive = true AND cj.isApproved = true AND cj.isExcluded = false AND cj.client IN (:all)')
                ->setParameter('cat', $category)->setParameter('all', array_map(static fn (Client $c) => (int) $c->getId(), $visible))
                ->getQuery()->getArrayResult();
            foreach ($found as $r) {
                $has[(int) $r['cid']][(int) $r['jid']] = true;
            }
            $jobs = $this->categoryJobs($category);
        }

        // rok na neradni dan kasni tek od prvog radnog dana posle njega (WorkingDayService::overdueCutoff), isto važi i za "uskoro"
        $today = $this->workingDays->overdueCutoff($this->today());
        $taskFilter = $this->taskFilter($me, $f);
        $agg = [];
        if (null !== $category) {
            foreach ($this->tasks->matrixAggregatesByJob($taskFilter, $me, $ids, (int) $category->getId(), $today, $this->soonUntil()) as $r) {
                $agg[$r['clientId']][$r['jobId']] = $r;
            }
        }
        $overdueTasks = $this->tasks->matrixOverdueCount($taskFilter, $me, $ids, $today);

        // kolone: poslovi koje neki vidljiv klijent ima ili po kojima postoje zadaci u periodu
        $used = [];
        foreach ($has as $byJob) {
            $used += $byJob;
        }
        foreach ($agg as $byJob) {
            $used += array_fill_keys(array_keys($byJob), true);
        }
        $columns = array_values(array_filter($jobs, static fn (JobTemplate $j) => isset($used[(int) $j->getId()])));

        $empty = $this->cell(0, 0, 0, 0);
        $rows = [];
        $danger = $warning = 0;
        foreach ($candidates as $client) {
            $cid = (int) $client->getId();
            $cells = [];
            foreach ($columns as $j) {
                $jid = (int) $j->getId();
                $a = $agg[$cid][$jid] ?? null;
                $cells[$jid] = null === $a ? $empty : $this->cell($a['total'], $a['done'], $a['overdue'], $a['soon']);
                $danger += MatrixState::DANGER === $cells[$jid]->state ? 1 : 0;
                $warning += MatrixState::WARNING === $cells[$jid]->state ? 1 : 0;
            }
            $rows[] = ['client' => $client, 'primary' => $client->getPrimaryAccountant(), 'backup' => $client->getBackupAccountant(), 'cells' => $cells];
        }
        // "samo nezavršeno" i "samo problematični" računaju se samo nad poslovima izabrane kategorije
        if ($f->onlyOpen) {
            $rows = array_values(array_filter($rows, static fn (array $r) => [] !== array_filter($r['cells'], static fn (MatrixCell $c) => $c->open() > 0)));
        }
        if ($f->onlyProblem) {
            $rows = array_values(array_filter($rows, static fn (array $r) => [] !== array_filter($r['cells'], static fn (MatrixCell $c) => $c->state->isProblem())));
        }
        $total = \count($rows);
        $rows = \array_slice($rows, max(0, $page - 1) * $perPage, $perPage);

        return new MatrixView($f, $columns, $rows, $total, $page, $perPage, $danger, $warning, $overdueTasks, $people, $allCategories, $category, $this->warningDays);
    }

    /**
     * @param list<JobCategory> $active aktivne kategorije po redosledu
     */
    private function resolveCategory(?int $id, array $active): ?JobCategory
    {
        foreach ($active as $c) {
            if (null !== $id && $c->getId() === $id) {
                return $c;
            }
        }

        return $active[0] ?? null;
    }

    /** Aktivni poslovi kategorije; JobTemplate nema polje za redosled, pa idu po nazivu */
    private function categoryJobs(JobCategory $category): array
    {
        return $this->em->getRepository(JobTemplate::class)->findBy(['category' => $category, 'isActive' => true], ['name' => 'ASC']);
    }

    /** Isti filter vidljivosti kao lista zadataka: admin i šef vide ceo svoj opseg ('all'), radnik samo svoje */
    private function taskFilter(User $me, MatrixFilters $f): TaskFilter
    {
        return new TaskFilter(
            viewerAdmin: $this->taskService->isAdmin($me),
            viewerLeader: $this->taskService->isLeader($me),
            scope: $this->taskService->isLeader($me) ? 'all' : 'mine',
            from: $f->from(),
            to: $f->to(),
            showClosed: true,
        );
    }

    /**
     * Zadaci jedne ćelije za prozor: ista vidljivost i isti period kao stanje ćelije; svi zadaci posla klijenta u periodu (npr. nedeljni poslovi).
     *
     * @return array{summary: string, state: string, total: int, done: int, groups: list<array{key: string, label: string, rows: list<array<string, mixed>>}>, more: int, listUrl: string, period: string}
     */
    public function cellDetails(User $me, Client $client, JobTemplate $job, MatrixFilters $f): array
    {
        $filter = $this->taskFilter($me, $f)->with(['client' => $client, 'job' => $job]);
        $today = $this->workingDays->overdueCutoff($this->today());
        $soonUntil = $this->soonUntil();

        $groups = ['overdue' => [], 'soon' => [], 'progress' => [], 'done' => []];
        $overdue = $soon = $done = 0;
        foreach ($this->tasks->matrixCellTasks($filter, $me) as $task) {
            $bucket = $this->bucket($task, $today, $soonUntil);
            $groups[$bucket][] = $task;
            $done += 'done' === $bucket ? 1 : 0;
            $overdue += 'overdue' === $bucket ? 1 : 0;
            $soon += 'soon' === $bucket ? 1 : 0;
        }
        $total = array_sum(array_map('count', $groups));
        $cell = $this->cell($total, $done, $overdue, $soon);

        $labels = ['overdue' => 'Kasni', 'soon' => 'Ističe uskoro', 'progress' => 'U toku', 'done' => 'Završeno'];
        $left = self::CELL_LIMIT;
        $out = [];
        foreach ($groups as $key => $list) {
            $rows = [];
            foreach (\array_slice($list, 0, $left) as $task) {
                $rows[] = $this->taskRow($task, $key);
            }
            $left -= \count($rows);
            if ([] !== $rows) {
                $out[] = ['key' => $key, 'label' => $labels[$key], 'rows' => $rows];
            }
        }

        return [
            'summary' => $cell->summary,
            'state' => $cell->state->value,
            'total' => $total,
            'done' => $done,
            'groups' => $out,
            'more' => max(0, $total - self::CELL_LIMIT),
            'listUrl' => $this->listUrl($me, $client, $job, $f),
            'period' => $this->periodLabel($f),
        ];
    }

    private function bucket(Task $task, \DateTimeImmutable $today, \DateTimeImmutable $soonUntil): string
    {
        if (TaskStatus::ZAVRSENO === $task->getStatus()) {
            return 'done';
        }
        $due = $task->getDueDate();
        if (null !== $due && $due < $today) {
            return 'overdue';
        }

        return null !== $due && $due <= $soonUntil ? 'soon' : 'progress';
    }

    /** @return array<string, mixed> */
    private function taskRow(Task $task, string $bucket): array
    {
        $people = [];
        foreach ($task->getAssignees() as $a) {
            $people[] = ['initials' => $a->getUser()->getInitials(), 'name' => $a->getUser()->getFullName()];
        }

        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'url' => $this->urls->generate('task_show', ['id' => $task->getId()]),
            'status' => $task->getStatus()->value,
            'statusLabel' => $task->getStatus()->label(),
            'due' => $task->getDueDate()?->format('d.m.Y.'),
            'overdue' => 'overdue' === $bucket,
            'people' => $people,
            'finishedAt' => 'done' === $bucket ? $task->getFinishedAt()?->format('d.m.Y.') : null,
        ];
    }

    /** Link "Otvori listu zadataka": klijent + posao + period (isti filteri kao brojevi; prikazuje i završene) */
    public function listUrl(User $me, Client $client, JobTemplate $job, MatrixFilters $f): string
    {
        $query = [
            'client' => $client->getId(),
            'job' => $job->getId(),
            'period' => $f->from()->format('Y-m-d').' do '.$f->to()->format('Y-m-d'),
            'closed' => 1,
        ];
        if ($this->taskService->isLeader($me) && !$this->taskService->isAdmin($me)) {
            $query['scope'] = 'all';
        }

        return $this->urls->generate('task_index', $query);
    }

    /**
     * Izbor perioda u filteru: 18 meseci unazad do 6 unapred od danas (i izabrani mesec, ako je van toga).
     *
     * @return list<array{value: string, label: string}>
     */
    public function periodOptions(MatrixFilters $f): array
    {
        $months = ['', 'januar', 'februar', 'mart', 'april', 'maj', 'jun', 'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar'];
        $first = $this->today()->modify('first day of this month');
        $keys = [];
        for ($i = -18; $i <= 6; ++$i) {
            $keys[] = $first->modify(sprintf('%+d months', $i))->format('Y-m');
        }
        if (!\in_array($f->periodKey(), $keys, true)) {
            $keys[] = $f->periodKey();
            sort($keys);
        }

        return array_map(static fn (string $k) => ['value' => $k, 'label' => $months[(int) substr($k, 5, 2)].' '.substr($k, 0, 4)], array_reverse($keys));
    }

    /** "novembar 2026" */
    public function periodLabel(MatrixFilters $f): string
    {
        $months = ['', 'januar', 'februar', 'mart', 'april', 'maj', 'jun', 'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar'];

        return $months[$f->month].' '.$f->year;
    }
}
