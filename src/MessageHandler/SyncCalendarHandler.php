<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Google\CalendarSync;
use App\Message\SyncCalendarEntity;
use App\Message\SyncUserCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sinhronizacija sa Google kalendarom (SPEC 8n.3). Neuspeh baca izuzetak, pa Messenger ponavlja (transport "calendar", do 5 puta);
 * korisnik dobija obaveštenje posle 5 neuspeha zaredom (GoogleCalendarConnection::MAX_FAILURES).
 */
final class SyncCalendarHandler
{
    public function __construct(private readonly CalendarSync $sync, private readonly EntityManagerInterface $em)
    {
    }

    #[AsMessageHandler]
    public function entity(SyncCalendarEntity $message): void
    {
        if ($this->sync->reconcile($message->entityType, $message->entityId) > 0) {
            // običan izuzetak: važi max_retries transporta (Recoverable bi se ponavljao bez kraja)
            throw new \RuntimeException('Google kalendar nije usklađen, pokušaće se ponovo.');
        }
    }

    #[AsMessageHandler]
    public function user(SyncUserCalendar $message): void
    {
        $user = $this->em->find(User::class, $message->userId);
        if (null !== $user) {
            $this->sync->resyncUser($user);
        }
    }
}
