<?php

namespace App\Google;

use App\Entity\GoogleCalendarConnection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Bira pravi Google API ili lažni kalendar (GOOGLE_CALENDAR_FAKE=1, samo u dev okruženju).
 */
#[AsAlias(CalendarGatewayInterface::class)]
final class SwitchingCalendarGateway implements CalendarGatewayInterface
{
    private CalendarGatewayInterface $inner;

    public function __construct(
        GoogleCalendarGateway $google,
        FakeCalendarGateway $fake,
        #[Autowire('%env(bool:default::GOOGLE_CALENDAR_FAKE)%')] ?bool $useFake,
        #[Autowire('%kernel.environment%')] string $environment,
    ) {
        $this->inner = $useFake && 'prod' !== $environment ? $fake : $google;
    }

    public function isConfigured(): bool
    {
        return $this->inner->isConfigured();
    }

    public function createCalendar(GoogleCalendarConnection $connection): string
    {
        return $this->inner->createCalendar($connection);
    }

    public function upsertEvent(GoogleCalendarConnection $connection, ?string $eventId, array $event): array
    {
        return $this->inner->upsertEvent($connection, $eventId, $event);
    }

    public function deleteEvent(GoogleCalendarConnection $connection, string $eventId): void
    {
        $this->inner->deleteEvent($connection, $eventId);
    }

    public function revoke(GoogleCalendarConnection $connection): void
    {
        $this->inner->revoke($connection);
    }
}
