<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\JobCategory;
use App\Entity\JobTemplate;
use App\Entity\User;
use App\Enum\MatrixState;
use App\Repository\ClientRepository;
use App\Repository\JobCategoryRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Matrica "Pregled poslova po klijentima" (SPEC 8r): redovi su klijenti koje korisnik vidi (ClientRepository::createVisibleQueryBuilder),
 * kolone su poslovi (JobTemplate) izabrane kategorije poslova (JobCategory), ćelija = status posla klijenta u izabranom mesecu
 * (zadaci sa rokom u tom mesecu): urađeno, kasni, u toku ili nema posla.
 * Brojevi idu kroz TaskRepository::createFilteredQueryBuilder, pa važi ista vidljivost zadataka kao u listi (radnik vidi samo svoje zadatke).
 * Upiti po prikazu: klijenti, poslovi klijenata u kategoriji, jedan agregat (GROUP BY klijent, posao) i spisak kategorija i poslova;
 * ne zavise od broja redova (nema N+1).
 */
class ClientJobMatrixService
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly JobCategoryRepository $categories,
        private readonly TaskRepository $tasks,
        private readonly ClientService $clientService,
        private readonly TaskService $taskService,
        private readonly WorkingDayService $workingDays,
        private readonly TextNormalizer $normalizer,
        private readonly EntityManagerInterface $em,
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

    /**
     * Jedino mesto gde se računa stanje ćelije (prioritet odozgo). Zadaci su već bez otkazanih; zakasnelo broji samo otvorene.
     * Zeleno samo kad su SVI zadaci posla u periodu urađeni; otvoren zadatak koji još ne kasni je neutralno (U toku).
     */
    public function cellState(int $total, int $done, int $overdue): MatrixState
    {
        return match (true) {
            0 === $total => MatrixState::NONE,
            $overdue > 0 => MatrixState::DANGER,
            $done === $total => MatrixState::SUCCESS,
            default => MatrixState::IN_PROGRESS,
        };
    }

    public function cell(int $total, int $done, int $overdue): MatrixCell
    {
        return new MatrixCell($total, $done, $overdue, $this->cellState($total, $done, $overdue));
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

        // rok na neradni dan kasni tek od prvog radnog dana posle njega (WorkingDayService::overdueCutoff)
        $today = $this->workingDays->overdueCutoff($this->today());
        $agg = [];
        if (null !== $category) {
            foreach ($this->tasks->matrixAggregatesByJob($this->taskFilter($me, $f), $me, $ids, (int) $category->getId(), $today) as $r) {
                $agg[$r['clientId']][$r['jobId']] = $r;
            }
        }

        // kolone: poslovi koje neki vidljiv klijent ima ili po kojima postoje zadaci u periodu
        $used = [];
        foreach ($has as $byJob) {
            $used += $byJob;
        }
        foreach ($agg as $byJob) {
            $used += array_fill_keys(array_keys($byJob), true);
        }
        $columns = array_values(array_filter($jobs, static fn (JobTemplate $j) => isset($used[(int) $j->getId()])));

        $empty = $this->cell(0, 0, 0);
        $rows = [];
        foreach ($candidates as $client) {
            $cid = (int) $client->getId();
            $cells = [];
            foreach ($columns as $j) {
                $a = $agg[$cid][(int) $j->getId()] ?? null;
                $cells[(int) $j->getId()] = null === $a ? $empty : $this->cell($a['total'], $a['done'], $a['overdue']);
            }
            $rows[] = ['client' => $client, 'primary' => $client->getPrimaryAccountant(), 'backup' => $client->getBackupAccountant(), 'cells' => $cells];
        }
        $total = \count($rows);
        $rows = \array_slice($rows, max(0, $page - 1) * $perPage, $perPage);

        return new MatrixView($f, $columns, $rows, $total, $page, $perPage, $people, $allCategories, $category);
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
