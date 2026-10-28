<?php

namespace App\Google;

use App\Entity\GoogleCalendarConnection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lažni Google kalendar za razvoj i proveru bez Google naloga (GOOGLE_CALENDAR_FAKE=1, samo dev): događaje upisuje
 * u var/google-fake.json. Kalendar sa ID-jem koji počinje sa "fail" simulira grešku Google-a.
 */
final class FakeCalendarGateway implements CalendarGatewayInterface
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/google-fake.json')] private readonly string $file)
    {
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createCalendar(GoogleCalendarConnection $connection): string
    {
        return 'fake-'.$connection->getUser()->getId();
    }

    public function upsertEvent(GoogleCalendarConnection $connection, ?string $eventId, array $event): array
    {
        $this->guard($connection);
        $data = $this->read();
        $eventId ??= 'ev'.bin2hex(random_bytes(6));
        $data[(string) $connection->getCalendarId()][$eventId] = ['title' => $event['title'], 'start' => $event['start']->format('Y-m-d'), 'end' => $event['end']->format('Y-m-d')];
        $this->write($data);

        return [$eventId, 'etag-'.substr(md5(json_encode($event['title']).$event['start']->format('Ymd')), 0, 8)];
    }

    public function deleteEvent(GoogleCalendarConnection $connection, string $eventId): void
    {
        $this->guard($connection);
        $data = $this->read();
        unset($data[(string) $connection->getCalendarId()][$eventId]);
        $this->write($data);
    }

    public function revoke(GoogleCalendarConnection $connection): void
    {
    }

    private function guard(GoogleCalendarConnection $connection): void
    {
        if (str_starts_with((string) $connection->getCalendarId(), 'fail')) {
            throw new \RuntimeException('Google kalendar (503): simulirana greška.');
        }
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function read(): array
    {
        return is_file($this->file) ? (array) json_decode((string) file_get_contents($this->file), true) : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(array $data): void
    {
        file_put_contents($this->file, json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }
}
