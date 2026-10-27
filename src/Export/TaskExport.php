<?php

namespace App\Export;

use App\Entity\Client;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Repository\ClientRepository;
use App\Repository\TaskRepository;
use App\Service\TaskService;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Izvoz liste zadataka (/tasks): isti tabovi, opseg i filteri kao TaskController::index i TASK_VIEW po redu.
 */
final class TaskExport implements ExportListInterface
{
    private const TAB_LABELS = ['all' => 'svi', 'today' => 'danas', 'week' => 'ova nedelja', 'overdue' => 'kasne', 'review' => 'na proveri', 'approval' => 'čeka moju potvrdu'];

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly ClientRepository $clients,
        private readonly TaskService $taskService,
        private readonly Security $security,
    ) {
    }

    public function getName(): string
    {
        return 'zadaci';
    }

    public function getTitle(): string
    {
        return 'Zadaci';
    }

    public function isAllowed(User $user): bool
    {
        return true;
    }

    public function getColumns(): array
    {
        return [
            ['label' => 'Zadatak', 'type' => 'text', 'width' => 44],
            ['label' => 'Klijent', 'type' => 'text', 'width' => 28],
            ['label' => 'Rok', 'type' => 'date', 'width' => 12],
            ['label' => 'Prioritet', 'type' => 'text', 'width' => 11],
            ['label' => 'Status', 'type' => 'text', 'width' => 14],
            ['label' => 'Učesnici', 'type' => 'text', 'width' => 30],
            ['label' => 'Završili deo', 'type' => 'number', 'width' => 12],
            ['label' => 'Checklist gotovo', 'type' => 'number', 'width' => 14],
            ['label' => 'Checklist ukupno', 'type' => 'number', 'width' => 14],
        ];
    }

    public function count(array $query, User $user): int
    {
        // Paginator broji tačno i kad upit ima HIDDEN kolone i parametre za sortiranje
        return \count(new Paginator($this->queryBuilder($query, $user)));
    }

    public function rows(array $query, User $user): iterable
    {
        foreach ($this->queryBuilder($query, $user)->getQuery()->getResult() as $task) {
            if (!$task instanceof Task || !$this->security->isGrantedForUser($user, 'TASK_VIEW', $task)) {
                continue;
            }
            yield [
                $task->getTitle(),
                $task->getClient()?->getName() ?? 'Interni',
                $task->getDueDate(),
                $task->getPriority()->label(),
                $task->getStatus()->label(),
                implode(', ', array_map(static fn (User $u) => $u->getFullName(), $task->getAssigneeUsers())),
                $task->getFinishedAssigneesCount(),
                $task->getChecklistDone(),
                $task->getChecklistTotal(),
            ];
        }
    }

    public function describeFilters(array $query): string
    {
        $tab = (string) ($query['tab'] ?? 'all');
        $parts = ['Prikaz: '.(self::TAB_LABELS[$tab] ?? 'svi')];
        if ('all' === ($query['scope'] ?? 'mine')) {
            $parts[] = 'svi zadaci u mom opsegu';
        }
        if ($priority = TaskPriority::tryFrom((string) ($query['priority'] ?? ''))) {
            $parts[] = 'Prioritet: '.$priority->label();
        }
        if (($id = (int) ($query['client'] ?? 0)) && $client = $this->clients->find($id)) {
            $parts[] = 'Klijent: '.$client->getName();
        }

        return implode(' · ', $parts);
    }

    /**
     * Ista logika filtera kao TaskController::index.
     *
     * @param array<string, mixed> $query
     */
    private function queryBuilder(array $query, User $user): QueryBuilder
    {
        $admin = $this->taskService->isAdmin($user);
        $leader = $this->taskService->isLeader($user);

        $tab = (string) ($query['tab'] ?? 'all');
        $tab = \in_array($tab, TaskRepository::TABS, true) ? $tab : 'all';
        $scope = ('all' === ($query['scope'] ?? 'mine') && $leader) ? 'all' : 'mine';
        $client = (int) ($query['client'] ?? 0) ? $this->clients->find((int) $query['client']) : null;

        if ('approval' === $tab && $leader) {
            return $this->tasks->createAwaitingApprovalQueryBuilder($user, $admin);
        }

        return $this->tasks->createListQueryBuilder($user, $admin, $leader, $scope, [
            'tab' => 'approval' === $tab ? 'all' : $tab,
            'client' => $client instanceof Client && $this->security->isGrantedForUser($user, 'CLIENT_VIEW', $client) ? $client : null,
            'priority' => TaskPriority::tryFrom((string) ($query['priority'] ?? '')),
        ]);
    }
}
