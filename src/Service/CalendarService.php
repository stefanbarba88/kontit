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
    public const TYPES = ['tasks', 'absences', 'holidays', 'todo'];

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly UserAbsenceRepository $absences,
        private readonly AbsenceOverview $overview,
        private readonly TaskService $taskService,
        private readonly WorkingDayService $workingDays,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
        private readonly AbsenceService $absenceService,
        private readonly \App\Repository\TodoItemRepository $todoItems,
        private readonly TodoClock $todoClock,
        private readonly \App\Service\ImpersonationState $impersonation,
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
            // radnik vidi i kada kolege iz tima nisu dostupne (samo odobreno, bez vrste i napomene)
            if (!$this->taskService->isLeader($me)) {
                $mates = $this->absenceService->teammates($me);
                if (!empty($filters['user'])) {
                    $mates = array_values(array_filter($mates, static fn (User $u) => $u->getId() === (int) $filters['user']));
                }
                foreach ($this->absences->findInPeriod($mates, $from, $to, false) as $absence) {
                    $events[] = $this->availabilityEvent($absence);
                }
            }
        }
        // Podsetnik: samo stavke OVOG korisnika (privatno), bez obzira na izabrani opseg; ne ulaze u ICS ni Google kalendar
        // za vreme "Prikaži kao korisnik" Podsetnik ne postoji ni u kalendaru (SPEC 8s)
        if (\in_array('todo', $types, true) && empty($filters['client']) && empty($filters['user']) && !$this->impersonation->isActive()) {
            $fromUtc = $this->todoClock->startOfDay($from->format('Y-m-d'));
            $toUtc = $this->todoClock->startOfDay($to->modify('+1 day')->format('Y-m-d'));
            foreach ($this->todoItems->findDueBetween($me, $fromUtc, $toUtc) as $item) {
                $events[] = $this->todoEvent($item);
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
        // rok na neradni dan koji još ne kasni (npr. subota, a danas je nedelja) ostaje vidljiv: prikazuje se u prvom danu pregleda
        $events = $this->events($me, min($start, $this->workingDays->overdueCutoff($start)), $end, ['scope' => $this->taskService->isLeader($me) ? 'team' : 'mine', 'types' => ['tasks', 'absences']]);
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
    /** @return array<string, mixed> */
    private function todoEvent(\App\Entity\TodoItem $item): array
    {
        $due = $item->getDueAt();
        $local = $this->todoClock->toLocal($due);

        return [
            'id' => 'todo-'.$item->getId(),
            'title' => $item->getTitle(),
            'start' => $item->hasDueTime() ? $local->format('Y-m-d\TH:i:s') : $local->format('Y-m-d'),
            'allDay' => !$item->hasDueTime(),
            'editable' => false,
            'classNames' => ['kt-fc-todo'],
            'extendedProps' => ['kind' => 'todo', 'time' => $item->hasDueTime() ? $local->format('H:i') : null, 'todoUrl' => $this->urls->generate('todo_index')],
        ];
    }

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

    /**
     * Odsustvo kolege za radnika: samo "nije dostupan" i period, bez vrste, statusa i napomene.
     *
     * @return array<string, mixed>
     */
    private function availabilityEvent(UserAbsence $absence): array
    {
        $user = $absence->getUser();
        $period = $absence->getStartDate() == $absence->getEndDate()
            ? $absence->getStartDate()->format('d.m.Y.')
            : $absence->getStartDate()->format('d.m.Y.').' – '.$absence->getEndDate()->format('d.m.Y.');

        return [
            'id' => 'v'.$absence->getId(),
            'title' => sprintf('%s · nije dostupan', $user->getFullName()),
            'start' => $absence->getStartDate()->format('Y-m-d'),
            'end' => $absence->getEndDate()->modify('+1 day')->format('Y-m-d'),
            'allDay' => true,
            'editable' => false,
            'backgroundColor' => 'var(--kt-text-3)',
            'borderColor' => 'var(--kt-text-3)',
            'classNames' => ['kt-fc-absence', 'is-approved'],
            'extendedProps' => [
                'kind' => 'absence',
                'initials' => $user->getInitials(),
                'type' => '',
                'status' => 'Nije dostupan',
                'summary' => $period,
                'color' => 'var(--kt-text-3)',
                'meta' => 'Nije dostupan',
            ],
        ];
    }
}
