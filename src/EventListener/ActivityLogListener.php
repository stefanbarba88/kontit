<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Entity\ChecklistItem;
use App\Entity\Client;
use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\TaskComment;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Enum\ActivityAction;
use App\Enum\AssigneeStatus;
use App\Enum\CommentType;
use App\Enum\CompletionMode;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Service\ActivityLogger;
use App\Service\ReplacementResolver;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Beleži promene na Task, ChecklistItem, TaskAssignee, TaskComment, Client i članstvo u timu u ActivityLog.
 *
 * onFlush prepoznaje šta se promenilo (changeset), ali ID novog entiteta još ne postoji,
 * pa se zapisi čuvaju u $pending i upisuju u postFlush-u, u istoj transakciji kao i sama izmena.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class ActivityLogListener
{
    private const TASK_FIELDS = [
        'title' => 'Naslov',
        'description' => 'Opis',
        'client' => 'Klijent',
        'dueDate' => 'Rok',
        'priority' => 'Prioritet',
        'completionMode' => 'Režim završetka',
    ];

    private const CLIENT_FIELDS = [
        'name' => 'Pun naziv',
        'shortName' => 'Kraći naziv',
        'postalCode' => 'Poštanski broj',
        'pib' => 'PIB',
        'maticniBroj' => 'Matični broj',
        'pdvObveznik' => 'PDV obveznik',
        'address' => 'Adresa',
        'city' => 'Grad',
        'contactPerson' => 'Kontakt osoba',
        'phone' => 'Telefon',
        'email' => 'Email',
        'startDate' => 'Klijent od',
        'note' => 'Napomena',
    ];

    /** @var list<array{subject: object, action: ActivityAction, changes: array<string, array{0: ?string, 1: ?string}>, user: ?User, type: string}> */
    private array $pending = [];

    private bool $flushing = false;

    public function __construct(
        private readonly Security $security,
        private readonly ActivityLogger $logger,
        private readonly ReplacementResolver $replacements,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->flushing) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();
        $user = $this->security->getUser();
        $user = $user instanceof User ? $user : null;

        $insertions = $uow->getScheduledEntityInsertions();

        // Zadaci koji se u ovom flush-u vraćaju na doradu: status i statusi učesnika se tada ne beleže posebno
        $returned = [];
        foreach ($insertions as $entity) {
            if ($entity instanceof TaskComment && CommentType::VRACANJE_NA_DORADU === $entity->getType()) {
                $returned[spl_object_id($entity->getTask())] = true;
            }
        }

        foreach ($insertions as $entity) {
            $this->inserted($entity, $user);
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->updated($entity, $uow->getEntityChangeSet($entity), $user, $returned);
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->deleted($entity, $user);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->flushing || [] === $this->pending) {
            return;
        }

        $entries = $this->pending;
        $this->pending = [];
        $this->flushing = true;

        try {
            /** @var EntityManagerInterface $em */
            $em = $args->getObjectManager();
            foreach ($entries as $entry) {
                [$id, $label] = $this->identify($entry['subject']);
                if (null !== $id) {
                    $em->persist(new ActivityLog($entry['type'], $id, $label, $entry['user'], $entry['action'], $entry['changes']));
                }
            }
            $em->flush();
        } finally {
            $this->flushing = false;
        }
    }

    private function inserted(object $entity, ?User $user): void
    {
        match (true) {
            $entity instanceof Task => $this->add($entity, ActivityAction::CREATED, ['Naslov' => [null, $entity->getTitle()]], $user),
            $entity instanceof TaskAssignee => $this->add($entity->getTask(), ActivityAction::ASSIGNED, ['Učesnik' => [null, $entity->getUser()->getFullName()]], $user),
            $entity instanceof ChecklistItem => $this->add($entity->getTask(), ActivityAction::UPDATED, ['Stavka checkliste' => [null, $entity->getText()]], $user),
            $entity instanceof TaskComment => $this->add(
                $entity->getTask(),
                CommentType::VRACANJE_NA_DORADU === $entity->getType() ? ActivityAction::RETURNED : ActivityAction::COMMENTED,
                [CommentType::VRACANJE_NA_DORADU === $entity->getType() ? 'Razlog' : 'Komentar' => [null, $this->logger->excerpt($entity->getBody())]],
                $user,
            ),
            $entity instanceof Client => $this->add($entity, ActivityAction::CREATED, ['Naziv' => [null, $entity->getName()]], $user),
            // odsustvo se beleži na timeline-u korisnika (SPEC 8j, 8m)
            $entity instanceof UserAbsence => $this->add($entity->getUser(), ActivityAction::CREATED, ['Odsustvo' => [null, $entity->summary().' · '.$entity->getStatus()->label()]], $user),
            $entity instanceof TeamMember => $this->add($entity->getUser(), ActivityAction::TEAM_CHANGED, ['Tim' => [null, $entity->getTeam()?->getName()]], $user),
            $entity instanceof User => $this->add($entity, ActivityAction::CREATED, ['Korisnik' => [null, $entity->getFullName()]], $user),
            default => null,
        };
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $cs
     * @param array<int, true>                         $returned
     */
    private function updated(object $entity, array $cs, ?User $user, array $returned): void
    {
        if ($entity instanceof Task) {
            $this->taskUpdated($entity, $cs, $user, $returned);
        } elseif ($entity instanceof TaskAssignee) {
            if (isset($cs['status']) && !isset($returned[spl_object_id($entity->getTask())])) {
                $change = $this->logger->diff($cs, ['status' => 'Učesnik '.$entity->getUser()->getFullName().(null !== $user && $user !== $entity->getUser() && $this->replacements->isSubstitute($entity->getTask(), $user) ? ' (zamena: '.$user->getFullName().')' : '')], ['status' => AssigneeStatus::class]);
                $this->add($entity->getTask(), ActivityAction::STATUS_CHANGED, $change, $user);
            }
        } elseif ($entity instanceof ChecklistItem) {
            $this->checklistUpdated($entity, $cs, $user);
        } elseif ($entity instanceof Client) {
            $this->clientUpdated($entity, $cs, $user);
        } elseif ($entity instanceof TeamMember) {
            if (isset($cs['leftAt']) && null === $cs['leftAt'][0] && null !== $cs['leftAt'][1]) {
                $this->add($entity->getUser(), ActivityAction::TEAM_CHANGED, ['Tim' => [$entity->getTeam()?->getName(), null]], $user);
            }
        } elseif ($entity instanceof User) {
            $this->userUpdated($entity, $cs, $user);
        } elseif ($entity instanceof UserAbsence && isset($cs['status'])) {
            // bez prijavljenog korisnika (komanda za automatsko odobravanje) zapis je "Sistem"
            $this->add($entity->getUser(), $entity->getStatus()->isApproved() ? ActivityAction::APPROVED : ActivityAction::STATUS_CHANGED, ['Odsustvo '.$entity->summary() => [($cs['status'][0] instanceof \App\Enum\AbsenceStatus ? $cs['status'][0] : \App\Enum\AbsenceStatus::tryFrom((string) $cs['status'][0]))?->label(), $entity->getStatus()->label()]], $user);
        }
    }

    private function deleted(object $entity, ?User $user): void
    {
        if ($entity instanceof TaskAssignee) {
            $this->add($entity->getTask(), ActivityAction::ASSIGNED, ['Učesnik' => [$entity->getUser()->getFullName(), null]], $user);
        } elseif ($entity instanceof ChecklistItem) {
            $this->add($entity->getTask(), ActivityAction::DELETED, ['Stavka checkliste' => [$entity->getText(), null]], $user);
        }
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $cs
     * @param array<int, true>                         $returned
     */
    private function taskUpdated(Task $task, array $cs, ?User $user, array $returned): void
    {
        if ([] !== ($changes = $this->logger->diff($cs, self::TASK_FIELDS, ['priority' => TaskPriority::class, 'completionMode' => CompletionMode::class]))) {
            $this->add($task, ActivityAction::UPDATED, $changes, $user);
        }

        if (!isset($cs['status'])) {
            return;
        }
        // changeset za enum kolone nosi sirovu vrednost
        $new = $cs['status'][1];
        $new = \is_string($new) ? TaskStatus::tryFrom($new) : $new;
        if (TaskStatus::U_RADU === $new && isset($returned[spl_object_id($task)])) {
            return;
        }

        $action = match ($new) {
            TaskStatus::NA_PROVERI => ActivityAction::SUBMITTED,
            TaskStatus::ZAVRSENO => ActivityAction::APPROVED,
            default => ActivityAction::STATUS_CHANGED,
        };
        $this->add($task, $action, $this->logger->diff($cs, ['status' => 'Status'], ['status' => TaskStatus::class]), $user);
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $cs
     */
    private function checklistUpdated(ChecklistItem $item, array $cs, ?User $user): void
    {
        $task = $item->getTask();

        if (isset($cs['isDone'])) {
            $this->add($task, $cs['isDone'][1] ? ActivityAction::CHECKED : ActivityAction::UNCHECKED, ['Stavka' => [null, $item->getText()]], $user);
        }
        if (isset($cs['assignedTo'])) {
            [$old, $new] = [$this->logger->format($cs['assignedTo'][0]) ?? 'zajednička', $this->logger->format($cs['assignedTo'][1]) ?? 'zajednička'];
            $this->add($task, ActivityAction::ASSIGNED, [sprintf('Stavka „%s“', $this->logger->excerpt($item->getText(), 60)) => [$old, $new]], $user);
        }
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $cs
     */
    private function clientUpdated(Client $client, array $cs, ?User $user): void
    {
        if ([] !== ($changes = $this->logger->diff($cs, self::CLIENT_FIELDS))) {
            $this->add($client, ActivityAction::UPDATED, $changes, $user);
        }
        if ([] !== ($changes = $this->logger->diff($cs, ['primaryAccountant' => 'Glavni zaduženi', 'backupAccountant' => 'Backup']))) {
            $this->add($client, ActivityAction::ASSIGNED, $changes, $user);
        }
        if ([] !== ($changes = $this->logger->diff($cs, ['isActive' => 'Aktivan']))) {
            $this->add($client, ActivityAction::STATUS_CHANGED, $changes, $user);
        }
    }

    /**
     * Lozinka, lični podaci i poslednja prijava se namerno ne beleže.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $cs
     */
    private function userUpdated(User $subject, array $cs, ?User $user): void
    {
        if ([] !== ($changes = $this->logger->diff($cs, ['isActive' => 'Aktivan']))) {
            $this->add($subject, ActivityAction::STATUS_CHANGED, $changes, $user);
        }
        if (isset($cs['roles'])) {
            $old = $this->logger->roleLabel((array) $cs['roles'][0]);
            $new = $this->logger->roleLabel((array) $cs['roles'][1]);
            if ($old !== $new) {
                $this->add($subject, ActivityAction::UPDATED, ['Rola' => [$old, $new]], $user);
            }
        }
    }

    /**
     * @param array<string, array{0: ?string, 1: ?string}> $changes
     */
    private function add(object $subject, ActivityAction $action, array $changes, ?User $user): void
    {
        if ([] === $changes && ActivityAction::CREATED !== $action) {
            return;
        }

        $this->pending[] = [
            'subject' => $subject,
            'action' => $action,
            'changes' => $changes,
            'user' => $user,
            'type' => match (true) {
                $subject instanceof Task => ActivityLog::TASK,
                $subject instanceof Client => ActivityLog::CLIENT,
                default => ActivityLog::USER,
            },
        ];
    }

    /**
     * ID i prikazni naziv entiteta, čita se tek u postFlush-u, kad novi entiteti već imaju ID.
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function identify(object $subject): array
    {
        return match (true) {
            $subject instanceof Task => [$subject->getId(), $subject->getTitle()],
            $subject instanceof Client => [$subject->getId(), $subject->getShortName()],
            $subject instanceof User => [$subject->getId(), $subject->getFullName()],
            default => [null, null],
        };
    }
}
