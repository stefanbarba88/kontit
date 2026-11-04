<?php

namespace App\Export;

use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskListFilters;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Izvoz liste zadataka (/tasks): isti tabovi, opseg i filteri kao TaskController::index (TaskListFilters) i TASK_VIEW po redu.
 */
final class TaskExport implements ExportListInterface
{
    public function __construct(
        private readonly TaskListFilters $filters,
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
            ['label' => 'Klijent (pun naziv)', 'type' => 'text', 'width' => 40],
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
                $task->getClient()?->getShortName() ?? 'Interni',
                $task->getClient()?->getName(),
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

    public function describeFilters(array $query, ?User $user = null): string
    {
        $user ??= $this->security->getUser();

        return $user instanceof User ? $this->filters->describe($this->filters->resolve($query, $user)) : '';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function queryBuilder(array $query, User $user): QueryBuilder
    {
        return $this->filters->queryBuilder($this->filters->resolve($query, $user), $user);
    }
}
