<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Repository\UserAbsenceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Događaji kalendara (SPEC 8n): zadaci po roku (po Voterima), odsustva (svoja / šef svoj tim / admin sva)
 * i neradni dani kao pozadina. Učitava se samo vidljivi opseg.
 */
class CalendarService
{
    public const SCOPES = ['mine', 'team', 'all'];
    public const TYPES = ['tasks', 'absences', 'holidays'];

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly UserAbsenceRepository $absences,
        private readonly AbsenceOverview $overview,
        private readonly TaskService $taskService,
        private readonly WorkingDayService $workingDays,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * Opsezi koje korisnik sme da izabere: svi "moji", šef "moj tim", admin i "svi".
     *
     * @return list<string>
     */
    public function scopesFor(User $me): array
    {
        return match (true) {
            $this->taskService->isAdmin($me) => ['mine', 'team', 'all'],
            $this->taskService->isLeader($me) => ['mine', 'team'],
            default => ['mine'],
        };
    }

    /**
     * @param array{scope?: string, types?: list<string>, client?: ?int, user?: ?int} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function events(User $me, \DateTimeImmutable $from, \DateTimeImmutable $to, array $filters = []): array
    {
        $scope = \in_array($filters['scope'] ?? 'mine', $this->scopesFor($me), true) ? $filters['scope'] : 'mine';
        $types = array_values(array_intersect($filters['types'] ?? self::TYPES, self::TYPES)) ?: self::TYPES;
        $events = [];

        if (\in_array('tasks', $types, true)) {
            foreach ($this->taskQuery($me, $scope, $from, $to, $filters) as $task) {
                $events[] = $this->taskEvent($task);
            }
        }
        if (\in_array('absences', $types, true) && empty($filters['client'])) {
            $users = match ($scope) {
                'mine' => [$me],
                'team' => $this->overview->usersFor($me),
                default => $this->overview->usersFor($me),
            };
            if (!empty($filters['user'])) {
                $users = array_values(array_filter($users, static fn (User $u) => $u->getId() === (int) $filters['user']));
            }
            foreach ($this->absences->findInPeriod($users, $from, $to) as $absence) {
                $events[] = $this->absenceEvent($absence);
            }
        }
        if (\in_array('holidays', $types, true)) {
            for ($y = (int) $from->format('Y'); $y <= (int) $to->format('Y'); ++$y) {
                foreach ($this->workingDays->specialDays($y) as $date => $day) {
                    if ($date >= $from->format('Y-m-d') && $date <= $to->format('Y-m-d')) {
                        $events[] = [
                            'id' => 'h'.$date,
                            'start' => $date,
                            'allDay' => true,
                            'display' => 'background',
                            'title' => $day['name'],
                            'classNames' => ['kt-fc-off', 'COLLECTIVE_LEAVE' === $day['type'] ? 'is-leave' : 'is-holiday'],
                            'extendedProps' => ['kind' => 'holiday', 'name' => $day['name']],
                        ];
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Mali pregled naredne nedelje za dashboard: zadaci sa rokom i odsustva, po danima.
     *
     * @return list<array{date: \DateTimeImmutable, off: ?string, items: list<array{kind: string, title: string, url: ?string, color: string, meta: string}>}>
     */
    public function week(User $me, ?\DateTimeImmutable $start = null): array
    {
        $start ??= new \DateTimeImmutable('today');
        $end = $start->modify('+6 days');
        $events = $this->events($me, $start, $end, ['scope' => $this->taskService->isLeader($me) ? 'team' : 'mine', 'types' => ['tasks', 'absences']]);
        $days = [];
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $days[$d->format('Y-m-d')] = ['date' => $d, 'off' => $this->workingDays->reason($d), 'items' => []];
        }
        foreach ($events as $e) {
            $first = new \DateTimeImmutable($e['start']);
            $last = isset($e['end']) ? (new \DateTimeImmutable($e['end']))->modify('-1 day') : $first;
            for ($d = max($first, $start); $d <= min($last, $end); $d = $d->modify('+1 day')) {
                $days[$d->format('Y-m-d')]['items'][] = [
                    'kind' => $e['extendedProps']['kind'],
                    'title' => $e['title'],
                    'url' => $e['url'] ?? null,
                    'color' => $e['extendedProps']['color'] ?? 'var(--kt-primary)',
                    'meta' => $e['extendedProps']['meta'] ?? '',
                ];
            }
        }

        return array_values($days);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<Task>
     */
    private function taskQuery(User $me, string $scope, \DateTimeImmutable $from, \DateTimeImmutable $to, array $filters): array
    {
        $admin = $this->taskService->isAdmin($me);
        $qb = match ($scope) {
            'all' => $this->tasks->createListQueryBuilder($me, $admin, true, 'all'),
            'team' => $this->tasks->createListQueryBuilder($me, false, true, 'all'),
            default => $this->tasks->createListQueryBuilder($me, false, false, 'mine'),
        };
        $qb->andWhere('t.dueDate BETWEEN :calFrom AND :calTo AND t.status <> :cancelled')
            ->setParameter('calFrom', $from)->setParameter('calTo', $to)
            ->setParameter('cancelled', TaskStatus::OTKAZANO);
        if (!empty($filters['client'])) {
            $qb->andWhere('t.client = :calClient')->setParameter('calClient', (int) $filters['client']);
        }
        if (!empty($filters['user'])) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\TaskAssignee cu WHERE cu.task = t AND cu.user = :calUser)')->setParameter('calUser', (int) $filters['user']);
        }

        // zaštita na nivou zadatka (isti Voter kao lista)
        return array_values(array_filter($qb->setMaxResults(800)->getQuery()->getResult(), fn (Task $t) => $this->security->isGranted('TASK_VIEW', $t)));
    }

    /**
     * @return array<string, mixed>
     */
    private function taskEvent(Task $task): array
    {
        $status = $task->getStatus();

        return [
            'id' => 't'.$task->getId(),
            'title' => $task->getTitle(),
            'start' => $task->getDueDate()->format('Y-m-d'),
            'allDay' => true,
            'url' => $this->urls->generate('task_show', ['id' => $task->getId()]),
            'editable' => !\in_array($status, [TaskStatus::ZAVRSENO, TaskStatus::OTKAZANO], true) && $this->security->isGranted('TASK_EDIT', $task),
            'classNames' => ['kt-fc-task', 'is-'.$status->color(), 'p-'.strtolower($task->getPriority()->value)],
            'extendedProps' => [
                'kind' => 'task',
                'taskId' => $task->getId(),
                'status' => $status->label(),
                'statusColor' => $status->color(),
                'priority' => $task->getPriority()->label(),
                'urgent' => 'HITAN' === $task->getPriority()->value,
                'client' => $task->getClient()?->getShortName() ?? 'Interni',
                'assignees' => implode(', ', array_map(static fn (User $u) => $u->getFullName(), $task->getAssigneeUsers())),
                'color' => 'var(--kt-'.$status->color().')',
                'meta' => ($task->getClient()?->getShortName() ?? 'Interni').' · '.$status->label(),
                'moveUrl' => $this->urls->generate('ajax_task_due', ['id' => $task->getId()]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function absenceEvent(UserAbsence $absence): array
    {
        $user = $absence->getUser();
        $type = $absence->getType();

        return [
            'id' => 'a'.$absence->getId(),
            'title' => sprintf('%s · %s', $user->getFullName(), $type->getName()),
            'start' => $absence->getStartDate()->format('Y-m-d'),
            // FullCalendar: kraj celodnevnog događaja je isključiv
            'end' => $absence->getEndDate()->modify('+1 day')->format('Y-m-d'),
            'allDay' => true,
            'editable' => false,
            'backgroundColor' => $type->getColor(),
            'borderColor' => $type->getColor(),
            'classNames' => ['kt-fc-absence', $absence->isPending() ? 'is-pending' : 'is-approved'],
            'extendedProps' => [
                'kind' => 'absence',
                'initials' => $user->getInitials(),
                'type' => $type->getName(),
                'status' => $absence->getStatus()->label(),
                'summary' => $absence->summary(),
                'color' => $type->getColor(),
                'meta' => $type->getName().($absence->isPending() ? ' (na odobrenju)' : ''),
            ],
        ];
    }
}
