<?php

namespace App\Service;

use App\Enum\NotificationType;
use App\Repository\TodoItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Šalje podsetnike iz Podsetnika (komanda app:todo:send-reminders, cron svakog minuta).
 * Tačno jednom po podsetniku: pre slanja se atomski postavlja reminder_sent_at (UPDATE ... WHERE reminder_sent_at IS NULL),
 * pa preklopljena ili dupla pokretanja ne mogu da pošalju isti podsetnik dvaput. Vreme dolazi iz TodoClock (UTC), ne iz baze.
 */
class TodoReminderService
{
    public function __construct(
        private readonly TodoItemRepository $items,
        private readonly TodoClock $clock,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return int broj poslatih podsetnika
     */
    public function sendDue(): int
    {
        $now = $this->clock->now();
        $sent = 0;
        foreach ($this->items->findDueReminderIds($now) as $id) {
            if (!$this->items->claimReminder($id, $now)) {
                continue; // drugi proces je već preuzeo
            }
            $item = $this->items->find($id);
            if (null === $item) {
                continue;
            }
            $this->em->refresh($item);
            $due = $this->todoDue($item);
            $this->notifications->notify(
                $item->getOwner(),
                NotificationType::TODO_REMINDER,
                'Podsetnik: '.$item->getTitle(),
                $due ?? 'Obaveštenje iz tvog Podsetnika.',
                ['todo_index'],
                null,
                $item->isRemindByEmail(),
            );
            ++$sent;
        }

        return $sent;
    }

    private function todoDue(\App\Entity\TodoItem $item): ?string
    {
        $due = $item->getDueAt();
        if (null === $due) {
            return null;
        }
        $local = $this->clock->toLocal($due);

        return 'Rok: '.$local->format('d.m.Y.').($item->hasDueTime() ? ' u '.$local->format('H:i') : '');
    }
}
