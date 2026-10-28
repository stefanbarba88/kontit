<?php

namespace App\EventListener;

use App\Entity\CalendarSyncLink;
use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\UserAbsence;
use App\Google\CalendarSync;
use App\Message\SyncCalendarEntity;
use App\Repository\GoogleCalendarConnectionRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Kad se promeni zadatak (naslov, rok, status, učesnici) ili odsustvo (status, period), šalje se poruka za usklađivanje
 * Google kalendara (SPEC 8n.3). Ništa se ne šalje dok niko nije povezao Google nalog.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class CalendarSyncListener
{
    private const TASK_FIELDS = ['title', 'dueDate', 'status', 'priority', 'client'];
    private const ABSENCE_FIELDS = ['status', 'startDate', 'endDate', 'type'];

    /** @var array<int, object> */
    private array $pending = [];

    private ?bool $anyConnection = null;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly CalendarSync $sync,
        private readonly GoogleCalendarConnectionRepository $connections,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collect($entity, null);
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collect($entity, $uow->getEntityChangeSet($entity));
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof TaskAssignee) {
                $this->pending[spl_object_id($entity->getTask())] = $entity->getTask();
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }
        $pending = $this->pending;
        $this->pending = [];
        if (!$this->enabled()) {
            return;
        }
        foreach ($pending as $entity) {
            if (null === $entity->getId()) {
                continue;
            }
            $this->bus->dispatch(new SyncCalendarEntity($entity instanceof Task ? CalendarSyncLink::TASK : CalendarSyncLink::ABSENCE, (int) $entity->getId()));
        }
    }

    /**
     * @param array<string, mixed>|null $changes null = nov zapis
     */
    private function collect(object $entity, ?array $changes): void
    {
        if ($entity instanceof Task && (null === $changes || [] !== array_intersect_key($changes, array_flip(self::TASK_FIELDS)))) {
            $this->pending[spl_object_id($entity)] = $entity;
        } elseif ($entity instanceof TaskAssignee && null === $changes) {
            $this->pending[spl_object_id($entity->getTask())] = $entity->getTask();
        } elseif ($entity instanceof UserAbsence && (null === $changes || [] !== array_intersect_key($changes, array_flip(self::ABSENCE_FIELDS)))) {
            $this->pending[spl_object_id($entity)] = $entity;
        }
    }

    private function enabled(): bool
    {
        return $this->sync->isActive() && ($this->anyConnection ??= $this->connections->count([]) > 0);
    }
}
