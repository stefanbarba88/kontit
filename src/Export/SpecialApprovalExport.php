<?php

namespace App\Export;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\TaskListFilters;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Izvoz tab-a "Potvrđeno" na stranici Posebne potvrde: isti upit (TaskRepository::createSpecialQueryBuilder) i ista prava kao lista.
 */
final class SpecialApprovalExport implements ExportListInterface
{
    public function __construct(
        private readonly TaskListFilters $filters,
        private readonly TaskRepository $tasks,
        private readonly Security $security,
    ) {
    }

    public function getName(): string
    {
        return 'posebnepotvrde';
    }

    public function getTitle(): string
    {
        return 'Posebne potvrde (potvrđeno)';
    }

    public function isAllowed(User $user): bool
    {
        return $this->security->isGrantedForUser($user, 'SPECIAL_APPROVALS_VIEW');
    }

    public function getColumns(): array
    {
        return [
            ['label' => 'Zadatak', 'type' => 'text', 'width' => 44],
            ['label' => 'Klijent', 'type' => 'text', 'width' => 28],
            ['label' => 'Posao', 'type' => 'text', 'width' => 30],
            ['label' => 'Učesnici', 'type' => 'text', 'width' => 30],
            ['label' => 'Potvrdio', 'type' => 'text', 'width' => 24],
            ['label' => 'Potvrđeno', 'type' => 'date', 'width' => 14],
        ];
    }

    public function count(array $query, User $user): int
    {
        return \count(new Paginator($this->queryBuilder($query, $user)));
    }

    public function rows(array $query, User $user): iterable
    {
        $all = $this->queryBuilder($query, $user)->getQuery()->getResult();
        $this->tasks->hydrateListRelations($all);
        foreach ($all as $task) {
            if (!$task instanceof Task || !$this->security->isGrantedForUser($user, 'TASK_VIEW', $task)) {
                continue;
            }
            yield [
                $task->getTitle(),
                $task->getClient()?->getShortName() ?? 'Interni',
                $task->getClientJob()?->getJobTemplate()->getName(),
                implode(', ', array_map(static fn (User $u) => $u->getFullName(), $task->getAssigneeUsers())),
                $task->getApprovedBy()?->getFullName(),
                $task->getApprovedAt(),
            ];
        }
    }

    public function describeFilters(array $query, ?User $user = null): string
    {
        $user ??= $this->security->getUser();
        if (!$user instanceof User) {
            return '';
        }
        $f = $this->resolve($query, $user);
        $parts = ['Posebne potvrde: potvrđeno'.($f->specialAll ? ' (sve)' : '')];
        if (null !== $f->client) {
            $parts[] = 'Klijent: '.$f->client->getShortName();
        }
        if (null !== $f->job) {
            $parts[] = 'Posao: '.$f->job->getName();
        }
        if (null !== $f->assignee) {
            $parts[] = 'Radnik: '.$f->assignee->getFullName();
        }
        if (null !== $f->from) {
            $parts[] = 'Potvrđeno: '.$f->from->format('d.m.Y.').($f->to != $f->from ? ' – '.$f->to?->format('d.m.Y.') : '');
        }

        return implode(' · ', $parts);
    }

    /** @param array<string, mixed> $query */
    private function resolve(array $query, User $user): \App\Service\TaskFilter
    {
        return $this->filters->resolveSpecial(['tab' => 'special_done'] + $query, $user)->with(['tab' => 'special_done']);
    }

    /** @param array<string, mixed> $query */
    private function queryBuilder(array $query, User $user): QueryBuilder
    {
        return $this->filters->queryBuilder($this->resolve($query, $user), $user);
    }
}
