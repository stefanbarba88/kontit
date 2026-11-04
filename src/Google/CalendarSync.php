<?php

namespace App\Google;

use App\Entity\CalendarSyncLink;
use App\Entity\GoogleCalendarConnection;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Enum\NotificationType;
use App\Enum\TaskStatus;
use App\Repository\CalendarSyncLinkRepository;
use App\Repository\GoogleCalendarConnectionRepository;
use App\Repository\TeamMemberRepository;
use App\Security\TeamScope;
use App\Service\NotificationService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sinhronizacija aplikacija → Google (SPEC 8n.3). Za zadatak ili odsustvo izračuna kome događaj treba da postoji
 * (učesnici zadatka; vlasnik odobrenog odsustva i, ako je uključeno, šef njegovog tima), pa ga napravi, izmeni ili obriše.
 * Idempotentno: ponovno pokretanje dovodi Google u isto stanje.
 */
class CalendarSync
{
    public function __construct(
        private readonly CalendarGatewayInterface $gateway,
        private readonly GoogleCalendarConnectionRepository $connections,
        private readonly CalendarSyncLinkRepository $links,
        private readonly TeamMemberRepository $members,
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
        private readonly UrlGeneratorInterface $urls,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function isActive(): bool
    {
        return $this->gateway->isConfigured() && '1' === $this->settings->get(SettingsService::GOOGLE_ENABLED);
    }

    /**
     * @return int broj neuspešnih korisnika (0 = sve je usklađeno)
     */
    public function reconcile(string $entityType, int $entityId): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        $entity = CalendarSyncLink::TASK === $entityType ? $this->em->find(Task::class, $entityId) : $this->em->find(UserAbsence::class, $entityId);
        [$users, $event] = match (true) {
            $entity instanceof Task => [$this->taskUsers($entity), $this->taskEvent($entity)],
            $entity instanceof UserAbsence => [$this->absenceUsers($entity), $this->absenceEvent($entity)],
            default => [[], null],
        };

        $existing = [];
        foreach ($this->links->findBy(['entityType' => $entityType, 'entityId' => $entityId]) as $link) {
            $existing[$link->getUser()->getId()] = $link;
        }

        $failures = 0;
        foreach ($users as $connection) {
            $user = $connection->getUser();
            try {
                $link = $existing[$user->getId()] ?? null;
                [$eventId, $etag] = $this->gateway->upsertEvent($connection, $link?->getGoogleEventId(), $event);
                if (null === $link) {
                    $this->em->persist(new CalendarSyncLink($user, $entityType, $entityId, $eventId, $etag));
                } else {
                    $link->touch($eventId, $etag);
                }
                $connection->markSynced();
                unset($existing[$user->getId()]);
            } catch (\RuntimeException $e) {
                ++$failures;
                $this->failed($connection, $e);
                unset($existing[$user->getId()]);
            }
        }

        // događaji koji više ne treba da postoje (zadatak otkazan, učesnik uklonjen, odsustvo otkazano ili odbijeno)
        foreach ($existing as $link) {
            $connection = $this->connections->findOneBy(['user' => $link->getUser()]);
            try {
                if (null !== $connection?->getCalendarId()) {
                    $this->gateway->deleteEvent($connection, $link->getGoogleEventId());
                }
                $this->em->remove($link);
            } catch (\RuntimeException $e) {
                ++$failures;
                $this->failed($connection, $e);
            }
        }
        $this->em->flush();

        return $failures;
    }

    /**
     * Ponovo šalje sve buduće događaje korisnika (posle povezivanja i za popravke).
     *
     * @return int broj usklađenih zapisa
     */
    public function resyncUser(User $user): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        $count = 0;
        $tasks = $this->em->createQuery('SELECT t.id FROM App\Entity\Task t JOIN App\Entity\TaskAssignee a WITH a.task = t WHERE a.user = :u AND t.dueDate >= :from AND t.status <> :cancelled')
            ->setParameters(new \Doctrine\Common\Collections\ArrayCollection([
                new \Doctrine\ORM\Query\Parameter('u', $user),
                new \Doctrine\ORM\Query\Parameter('from', new \DateTimeImmutable('-30 days')),
                new \Doctrine\ORM\Query\Parameter('cancelled', TaskStatus::OTKAZANO),
            ]))->getSingleColumnResult();
        foreach ($tasks as $id) {
            $this->reconcile(CalendarSyncLink::TASK, (int) $id);
            ++$count;
        }
        $absences = $this->em->createQuery('SELECT a.id FROM App\Entity\UserAbsence a WHERE a.user = :u AND a.endDate >= :today')
            ->setParameter('u', $user)->setParameter('today', new \DateTimeImmutable('today'))->getSingleColumnResult();
        foreach ($absences as $id) {
            $this->reconcile(CalendarSyncLink::ABSENCE, (int) $id);
            ++$count;
        }

        return $count;
    }

    /**
     * Odvezivanje: Google pristup se opoziva, a veza i mapiranja brišu (kalendar "KontIT" ostaje korisniku).
     */
    public function disconnect(User $user): void
    {
        $connection = $this->connections->findOneBy(['user' => $user]);
        if (null === $connection) {
            return;
        }
        $this->gateway->revoke($connection);
        foreach ($this->links->findBy(['user' => $user]) as $link) {
            $this->em->remove($link);
        }
        $this->em->remove($connection);
        $this->em->flush();
    }

    /**
     * @return list<GoogleCalendarConnection>
     */
    private function taskUsers(Task $task): array
    {
        if (TaskStatus::OTKAZANO === $task->getStatus() || null === $task->getDueDate()) {
            return [];
        }

        return $this->connectionsFor($task->getAssigneeUsers());
    }

    /**
     * @return list<GoogleCalendarConnection>
     */
    private function absenceUsers(UserAbsence $absence): array
    {
        if (!$absence->isApproved()) {
            return [];
        }
        $users = [$absence->getUser()];
        if ('1' === $this->settings->get(SettingsService::GOOGLE_LEADER_TEAM)) {
            $leader = $this->members->findActiveByUser($absence->getUser())?->getTeam()?->getLeader();
            // šef dobija odsustva radnika iz tima, ne i člana koji je šef ili admin (TeamScope)
            if (null !== $leader && $leader !== $absence->getUser() && TeamScope::covers($leader, $absence->getUser())) {
                $users[] = $leader;
            }
        }

        return $this->connectionsFor($users);
    }

    /**
     * @param list<User> $users
     *
     * @return list<GoogleCalendarConnection>
     */
    private function connectionsFor(array $users): array
    {
        if ([] === $users) {
            return [];
        }

        return array_values(array_filter(
            $this->connections->findBy(['user' => $users]),
            static fn (GoogleCalendarConnection $c) => null !== $c->getCalendarId() && $c->getUser()->isActive(),
        ));
    }

    /**
     * @return array{title: string, description: string, start: \DateTimeImmutable, end: \DateTimeImmutable, url: ?string}|null
     */
    private function taskEvent(Task $task): ?array
    {
        if (null === $task->getDueDate()) {
            return null;
        }
        $url = $this->urls->generate('task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            'title' => (TaskStatus::ZAVRSENO === $task->getStatus() ? '✓ ' : '').$task->getTitle(),
            'description' => sprintf("%s\nStatus: %s · %s\n%s", $task->getClient()?->getShortName() ?? 'Interni zadatak', $task->getStatus()->label(), $task->getPriority()->label(), $url),
            'start' => $task->getDueDate(),
            'end' => $task->getDueDate()->modify('+1 day'),
            'url' => $url,
        ];
    }

    /**
     * @return array{title: string, description: string, start: \DateTimeImmutable, end: \DateTimeImmutable, url: ?string}
     */
    private function absenceEvent(UserAbsence $absence): array
    {
        return [
            'title' => sprintf('%s · %s', $absence->getUser()->getFullName(), $absence->getType()->getName()),
            'description' => $absence->summary(),
            'start' => $absence->getStartDate(),
            'end' => $absence->getEndDate()->modify('+1 day'),
            'url' => null,
        ];
    }

    private function failed(?GoogleCalendarConnection $connection, \RuntimeException $e): void
    {
        if (null === $connection) {
            return;
        }
        if ($connection->markFailed($e->getMessage())) {
            $this->notifications->notify($connection->getUser(), NotificationType::CALENDAR_SYNC_FAILED, 'Google kalendar ne može da se osveži', sprintf('Posle %d pokušaja događaji se ne upisuju u tvoj Google kalendar: %s Proveri vezu na profilu ili je poveži ponovo.', GoogleCalendarConnection::MAX_FAILURES, $e->getMessage()), ['app_profile']);
        }
    }
}
