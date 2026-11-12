<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\JobTemplate;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\ClientRepository;
use App\Repository\TaskRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Pravi TaskFilter iz GET parametara za listu zadataka (/tasks, tab klijenta, tab korisnika); zajednički za listu i izvoz (CLAUDE.md).
 * Admin+ podrazumevano vidi sve zadatke i filtrira po timu, radniku, klijentu, statusu, prioritetu i periodu (rok).
 * Šef bira između svojih zadataka i zadataka svog tima; tim i radnik su samo iz njegovog opsega. Radnik vidi samo svoje.
 * Na stranici klijenta/korisnika taj filter je zaključan, a opseg je najširi koji korisniku pripada (vidljivost određuje repozitorijum).
 */
class TaskListFilters
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly ClientRepository $clients,
        private readonly TeamRepository $teams,
        private readonly UserRepository $users,
        private readonly TaskService $taskService,
        private readonly Security $security,
        private readonly TextNormalizer $normalizer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function resolve(array $query, User $me, ?Client $lockedClient = null, ?User $lockedAssignee = null): TaskFilter
    {
        $admin = $this->taskService->isAdmin($me);
        $leader = $this->taskService->isLeader($me);
        $locked = null !== $lockedClient || null !== $lockedAssignee;

        $tab = (string) ($query['tab'] ?? 'all');
        $tab = !$locked && \in_array($tab, TaskRepository::TABS, true) && ('approval' !== $tab || $leader) ? $tab : 'all';
        // admin podrazumevano vidi sve zadatke, šef svoje (može da izabere ceo tim); na stranici klijenta/korisnika najširi opseg
        $scope = $locked ? ($leader ? 'all' : 'mine') : (string) ($query['scope'] ?? ($admin ? 'all' : 'mine'));
        $scope = 'all' === $scope && $leader ? 'all' : 'mine';

        $client = $lockedClient;
        if (null === $client && (int) ($query['client'] ?? 0)) {
            $client = $this->clients->find((int) $query['client']);
            if (null !== $client && !$this->security->isGrantedForUser($me, 'CLIENT_VIEW', $client)) {
                $client = null;
            }
        }

        $team = null;
        $teamId = (int) ($query['team'] ?? 0);
        if ($teamId && !$locked) {
            $team = array_values(array_filter($this->teamsFor($me), static fn (Team $t) => $t->getId() === $teamId))[0] ?? null;
        }
        $assignee = $lockedAssignee;
        $userId = (int) ($query['user'] ?? 0);
        if (null === $assignee && $userId) {
            $assignee = array_values(array_filter($this->usersFor($me), static fn (User $u) => $u->getId() === $userId))[0] ?? null;
        }

        // period roka: "2026-11-01 do 2026-11-30" (flatpickr opseg) ili jedan dan
        preg_match_all('/\d{4}-\d{2}-\d{2}/', (string) ($query['period'] ?? ''), $dates);
        $from = isset($dates[0][0]) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dates[0][0]) ?: null : null;
        $to = isset($dates[0][1]) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dates[0][1]) ?: null : $from;
        if (null !== $from && null !== $to && $to < $from) {
            [$from, $to] = [$to, $from];
        }

        $qRaw = mb_substr(trim((string) ($query['q'] ?? '')), 0, 100);
        $jobParam = (string) ($query['job'] ?? '');
        $job = ctype_digit($jobParam) && '' !== $jobParam ? $this->em->find(JobTemplate::class, (int) $jobParam) : null;
        $origin = (string) ($query['origin'] ?? 'all');
        $role = (string) ($query['role'] ?? 'assigned');

        return new TaskFilter(
            viewerAdmin: $admin,
            viewerLeader: $leader,
            scope: $scope,
            tab: $tab,
            client: $client,
            priority: TaskPriority::tryFrom((string) ($query['priority'] ?? '')),
            team: $team,
            assignee: $assignee,
            status: TaskStatus::tryFrom((string) ($query['status'] ?? '')),
            from: $from,
            to: $to,
            q: $this->normalizer->normalize($qRaw),
            qRaw: $qRaw,
            job: $job,
            noJob: 'none' === $jobParam,
            origin: \in_array($origin, TaskFilter::ORIGINS, true) ? $origin : 'all',
            showClosed: \in_array((string) ($query['closed'] ?? ''), ['1', 'on', 'true'], true),
            role: null !== $lockedAssignee && \in_array($role, TaskFilter::ROLES, true) ? $role : 'assigned',
            lockedClient: $lockedClient,
            lockedAssignee: $lockedAssignee,
        );
    }

    /**
     * Filteri stranice Posebne potvrde: isti parametri kao lista zadataka, ali tab je special_waiting | special_progress | special_done,
     * a opseg određuje TaskRepository (proveravač je $me; superadmin sa prekidačem "all" vidi sve).
     *
     * @param array<string, mixed> $query
     */
    public function resolveSpecial(array $query, User $me): TaskFilter
    {
        $tab = (string) ($query['tab'] ?? 'special_waiting');
        $tab = \in_array($tab, TaskRepository::SPECIAL_TABS, true) ? $tab : 'special_waiting';
        $base = $this->resolve(array_diff_key($query, ['tab' => 1]), $me);
        $all = \in_array((string) ($query['all'] ?? ''), ['1', 'on', 'true'], true) && \App\Enum\Role::SUPERADMIN === $me->getHighestRole();

        return $base->with(['tab' => $tab, 'specialApprovalFor' => $me, 'specialAll' => $all, 'scope' => 'all', 'showClosed' => true]);
    }

    /**
     * Za izvoz i druge pozive sa istim GET parametrima kao stranica: forClient / forUser zaključavaju klijenta / korisnika
     * (uz proveru CLIENT_VIEW odnosno USER_TASKS_VIEW), pa izvoz sa taba daje tačno ono što tab prikazuje.
     *
     * @param array<string, mixed> $query
     */
    public function resolveFromQuery(array $query, User $me): TaskFilter
    {
        $client = (int) ($query["forClient"] ?? 0) ? $this->clients->find((int) $query["forClient"]) : null;
        if (null !== $client && !$this->security->isGrantedForUser($me, "CLIENT_VIEW", $client)) {
            $client = null;
        }
        $user = (int) ($query["forUser"] ?? 0) ? $this->users->find((int) $query["forUser"]) : null;
        if (null !== $user && !$this->security->isGrantedForUser($me, "USER_TASKS_VIEW", $user)) {
            $user = null;
        }

        return $this->resolve($query, $me, $client, $user);
    }

    /**
     * Jedini upit liste za razrešene filtere: vidljivost + filteri (TaskRepository::createFilteredQueryBuilder).
     */
    public function queryBuilder(TaskFilter $f, User $me): QueryBuilder
    {
        return $this->tasks->createFilteredQueryBuilder($f, $me);
    }

    /**
     * Poslovi za filter "Posao", grupisano po kategoriji: ceo katalog, a na stranici klijenta samo poslovi koje klijent ima ili je imao.
     *
     * @return array<string, list<JobTemplate>> naziv kategorije => šabloni
     */
    public function jobOptions(?Client $client = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('jt', 'jc')->from(JobTemplate::class, 'jt')->leftJoin('jt.category', 'jc')
            ->orderBy('jc.position')->addOrderBy('jc.name')->addOrderBy('jt.name');
        if (null !== $client) {
            $qb->where('EXISTS (SELECT 1 FROM App\Entity\ClientJob cj WHERE cj.jobTemplate = jt AND cj.client = :client)')->setParameter('client', $client);
        } else {
            $qb->where('jt.isActive = true OR EXISTS (SELECT 1 FROM App\Entity\ClientJob cj2 WHERE cj2.jobTemplate = jt)');
        }

        $grouped = [];
        foreach ($qb->getQuery()->getResult() as $template) {
            $grouped[$template->getCategory()?->getName() ?? 'Bez kategorije'][] = $template;
        }

        return $grouped;
    }

    /**
     * Timovi za filter: admin svi, šef oni koje vodi, radnik nijedan.
     *
     * @return list<Team>
     */
    public function teamsFor(User $me): array
    {
        if (!$this->taskService->isLeader($me)) {
            return [];
        }
        $all = $this->teams->createListQueryBuilder()->getQuery()->getResult();

        return $this->taskService->isAdmin($me) ? $all : array_values(array_filter($all, static fn (Team $t) => $t->getLeader() === $me));
    }

    /**
     * Radnici za filter: admin svi aktivni, šef on i članovi timova koje vodi, radnik nijedan.
     *
     * @return list<User>
     */
    public function usersFor(User $me): array
    {
        if (!$this->taskService->isLeader($me)) {
            return [];
        }

        return $this->users->createAssignableQueryBuilder($me, $this->taskService->isAdmin($me))->getQuery()->getResult();
    }

    /**
     * Opis filtera za zaglavlje izvoza.
     */
    public function describe(TaskFilter $f): string
    {
        $tabs = ['all' => 'svi', 'today' => 'danas', 'week' => 'ova nedelja', 'overdue' => 'kasne', 'review' => 'na proveri', 'approval' => 'čeka moju potvrdu'];
        $parts = ['Prikaz: '.($tabs[$f->tab] ?? 'svi')];
        if ('all' === $f->scope && null === $f->lockedClient && null === $f->lockedAssignee) {
            $parts[] = 'svi zadaci u mom opsegu';
        }
        if (null !== $f->team) {
            $parts[] = 'Tim: '.$f->team->getName();
        }
        if (null !== $f->assignee) {
            $parts[] = ('created' === $f->role ? 'Kreirao: ' : ('both' === $f->role ? 'Zadužen ili kreirao: ' : 'Radnik: ')).$f->assignee->getFullName();
        }
        if (null !== $f->client) {
            $parts[] = 'Klijent: '.$f->client->getShortName();
        }
        if (null !== $f->status) {
            $parts[] = 'Status: '.$f->status->label();
        } elseif ('all' === $f->tab && !$f->showClosed) {
            $parts[] = 'samo otvoreni';
        }
        if (null !== $f->priority) {
            $parts[] = 'Prioritet: '.$f->priority->label();
        }
        if ('' !== $f->qRaw) {
            $parts[] = 'Naziv: „'.$f->qRaw.'“';
        }
        if ($f->noJob) {
            $parts[] = 'Posao: samostalni zadaci';
        } elseif (null !== $f->job) {
            $parts[] = 'Posao: '.$f->job->getName();
        }
        if ('all' !== $f->origin) {
            $parts[] = 'Nastanak: '.('auto' === $f->origin ? 'automatski' : 'ručno');
        }
        if (null !== $f->from) {
            $parts[] = 'Rok: '.$f->from->format('d.m.Y.').($f->to != $f->from ? ' – '.$f->to?->format('d.m.Y.') : '');
        }

        return implode(' · ', $parts);
    }
}
