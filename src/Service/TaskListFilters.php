<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\ClientRepository;
use App\Repository\TaskRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Filteri liste zadataka (/tasks), zajednički za listu i izvoz (isti upit, CLAUDE.md).
 * Admin+ podrazumevano vidi sve zadatke i filtrira po timu, radniku, klijentu, statusu, prioritetu i periodu (rok).
 * Šef bira između svojih zadataka i zadataka svog tima; tim i radnik su samo iz njegovog opsega. Radnik vidi samo svoje.
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
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{tab: string, scope: string, client: ?Client, priority: ?TaskPriority, team: ?Team, assignee: ?User, status: ?TaskStatus, from: ?\DateTimeImmutable, to: ?\DateTimeImmutable, period: string}
     */
    public function resolve(array $query, User $me): array
    {
        $admin = $this->taskService->isAdmin($me);
        $leader = $this->taskService->isLeader($me);

        $tab = (string) ($query['tab'] ?? 'all');
        $tab = \in_array($tab, TaskRepository::TABS, true) && ('approval' !== $tab || $leader) ? $tab : 'all';
        // admin podrazumevano vidi sve zadatke, šef svoje (može da izabere ceo tim)
        $scope = (string) ($query['scope'] ?? ($admin ? 'all' : 'mine'));
        $scope = 'all' === $scope && $leader ? 'all' : 'mine';

        $client = (int) ($query['client'] ?? 0) ? $this->clients->find((int) $query['client']) : null;
        if (null !== $client && !$this->security->isGrantedForUser($me, 'CLIENT_VIEW', $client)) {
            $client = null;
        }

        $teamId = (int) ($query['team'] ?? 0);
        $team = $teamId ? (array_values(array_filter($this->teamsFor($me), static fn (Team $t) => $t->getId() === $teamId))[0] ?? null) : null;
        $userId = (int) ($query['user'] ?? 0);
        $assignee = $userId ? (array_values(array_filter($this->usersFor($me), static fn (User $u) => $u->getId() === $userId))[0] ?? null) : null;

        // period roka: "2026-11-01 do 2026-11-30" (flatpickr opseg) ili jedan dan
        preg_match_all('/\d{4}-\d{2}-\d{2}/', (string) ($query['period'] ?? ''), $dates);
        $from = isset($dates[0][0]) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dates[0][0]) ?: null : null;
        $to = isset($dates[0][1]) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dates[0][1]) ?: null : $from;
        if (null !== $from && null !== $to && $to < $from) {
            [$from, $to] = [$to, $from];
        }

        return [
            'tab' => $tab,
            'scope' => $scope,
            'client' => $client,
            'priority' => TaskPriority::tryFrom((string) ($query['priority'] ?? '')),
            'team' => $team,
            'assignee' => $assignee,
            'status' => TaskStatus::tryFrom((string) ($query['status'] ?? '')),
            'from' => $from,
            'to' => $to,
            'period' => null === $from ? '' : $from->format('Y-m-d').($to != $from ? ' do '.$to->format('Y-m-d') : ''),
        ];
    }

    /**
     * Upit liste za razrešene filtere ($f iz resolve()).
     *
     * @param array<string, mixed> $f
     */
    public function queryBuilder(array $f, User $me): QueryBuilder
    {
        $admin = $this->taskService->isAdmin($me);
        $leader = $this->taskService->isLeader($me);

        $qb = 'approval' === $f['tab']
            ? $this->tasks->createAwaitingApprovalQueryBuilder($me, $admin)
            : $this->tasks->createListQueryBuilder($me, $admin, $leader, $f['scope'], ['tab' => $f['tab']]);

        return $this->tasks->applyFilters($qb, $f);
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
     *
     * @param array<string, mixed> $f
     */
    public function describe(array $f): string
    {
        $tabs = ['all' => 'svi', 'today' => 'danas', 'week' => 'ova nedelja', 'overdue' => 'kasne', 'review' => 'na proveri', 'approval' => 'čeka moju potvrdu'];
        $parts = ['Prikaz: '.($tabs[$f['tab']] ?? 'svi')];
        if ('all' === $f['scope']) {
            $parts[] = 'svi zadaci u mom opsegu';
        }
        if (null !== $f['team']) {
            $parts[] = 'Tim: '.$f['team']->getName();
        }
        if (null !== $f['assignee']) {
            $parts[] = 'Radnik: '.$f['assignee']->getFullName();
        }
        if (null !== $f['client']) {
            $parts[] = 'Klijent: '.$f['client']->getShortName();
        }
        if (null !== $f['status']) {
            $parts[] = 'Status: '.$f['status']->label();
        }
        if (null !== $f['priority']) {
            $parts[] = 'Prioritet: '.$f['priority']->label();
        }
        if (null !== $f['from']) {
            $parts[] = 'Rok: '.$f['from']->format('d.m.Y.').($f['to'] != $f['from'] ? ' – '.$f['to']->format('d.m.Y.') : '');
        }

        return implode(' · ', $parts);
    }
}
