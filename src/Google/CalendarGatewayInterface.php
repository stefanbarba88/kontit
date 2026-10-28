<?php

namespace App\Google;

use App\Entity\GoogleCalendarConnection;

/**
 * Pristup Google Calendar API-ju za jednu vezu (SPEC 8n.3). Izdvojeno da bi se logika sinhronizacije testirala bez mreže.
 * Greška u komunikaciji baca \RuntimeException.
 */
interface CalendarGatewayInterface
{
    public function isConfigured(): bool;

    /**
     * Pravi poseban kalendar "KontIT" i vraća njegov ID.
     */
    public function createCalendar(GoogleCalendarConnection $connection): string;

    /**
     * Pravi ili menja celodnevni događaj; vraća [id događaja, etag].
     *
     * @param array{title: string, description: string, start: \DateTimeImmutable, end: \DateTimeImmutable, url: ?string} $event kraj je isključiv
     *
     * @return array{0: string, 1: ?string}
     */
    public function upsertEvent(GoogleCalendarConnection $connection, ?string $eventId, array $event): array;

    public function deleteEvent(GoogleCalendarConnection $connection, string $eventId): void;

    public function revoke(GoogleCalendarConnection $connection): void;
}
