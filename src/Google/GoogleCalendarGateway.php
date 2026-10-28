<?php

namespace App\Google;

use App\Entity\GoogleCalendarConnection;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Exception as GoogleException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Google Calendar API preko google/apiclient (samo Calendar servis). Pristupni token se obnavlja refresh tokenom
 * kad istekne, a novi se odmah čuva šifrovan.
 */
class GoogleCalendarGateway implements CalendarGatewayInterface
{
    public const SCOPES = ['https://www.googleapis.com/auth/calendar.app.created', 'email'];

    public function __construct(
        private readonly TokenCipher $cipher,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(default::GOOGLE_CLIENT_ID)%')] private readonly ?string $clientId,
        #[Autowire('%env(default::GOOGLE_CLIENT_SECRET)%')] private readonly ?string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== (string) $this->clientId && '' !== (string) $this->clientSecret;
    }

    public function createCalendar(GoogleCalendarConnection $connection): string
    {
        $calendar = new GoogleCalendar();
        $calendar->setSummary('KontIT');
        $calendar->setDescription('Zadaci i odsustva iz KontIT app. Menja ih aplikacija; izmene ovde se ne vraćaju u aplikaciju.');
        $calendar->setTimeZone('Europe/Belgrade');

        return $this->call(fn (Calendar $api) => $api->calendars->insert($calendar)->getId(), $connection);
    }

    public function upsertEvent(GoogleCalendarConnection $connection, ?string $eventId, array $event): array
    {
        $item = new Event();
        $item->setSummary($event['title']);
        $item->setDescription($event['description']);
        $start = new EventDateTime();
        $start->setDate($event['start']->format('Y-m-d'));
        $end = new EventDateTime();
        $end->setDate($event['end']->format('Y-m-d'));
        $item->setStart($start);
        $item->setEnd($end);
        $item->setTransparency('transparent');
        if (null !== $event['url']) {
            $item->setSource(new \Google\Service\Calendar\EventSource(['title' => 'KontIT app', 'url' => $event['url']]));
        }

        $calendarId = (string) $connection->getCalendarId();

        return $this->call(function (Calendar $api) use ($calendarId, $eventId, $item): array {
            if (null !== $eventId) {
                try {
                    $saved = $api->events->update($calendarId, $eventId, $item);

                    return [$saved->getId(), $saved->getEtag()];
                } catch (GoogleException $e) {
                    // događaj je obrisan u Google-u: pravi se ponovo
                    if (!\in_array($e->getCode(), [404, 410], true)) {
                        throw $e;
                    }
                }
            }
            $saved = $api->events->insert($calendarId, $item);

            return [$saved->getId(), $saved->getEtag()];
        }, $connection);
    }

    public function deleteEvent(GoogleCalendarConnection $connection, string $eventId): void
    {
        $calendarId = (string) $connection->getCalendarId();
        $this->call(function (Calendar $api) use ($calendarId, $eventId): void {
            try {
                $api->events->delete($calendarId, $eventId);
            } catch (GoogleException $e) {
                if (!\in_array($e->getCode(), [404, 410], true)) {
                    throw $e;
                }
            }
        }, $connection);
    }

    public function revoke(GoogleCalendarConnection $connection): void
    {
        try {
            $this->client($connection)->revokeToken($this->cipher->decrypt($connection->getRefreshToken()) ?? $this->cipher->decrypt($connection->getAccessToken()));
        } catch (\Throwable) {
            // odvezivanje u aplikaciji uspeva i kad Google ne odgovori
        }
    }

    /**
     * @template T
     *
     * @param callable(Calendar): T $fn
     *
     * @return T
     */
    private function call(callable $fn, GoogleCalendarConnection $connection): mixed
    {
        try {
            return $fn(new Calendar($this->client($connection)));
        } catch (GoogleException $e) {
            throw new \RuntimeException(sprintf('Google kalendar (%d): %s', $e->getCode(), $e->getMessage()), (int) $e->getCode(), $e);
        }
    }

    private function client(GoogleCalendarConnection $connection): Client
    {
        $client = new Client();
        $client->setClientId((string) $this->clientId);
        $client->setClientSecret((string) $this->clientSecret);
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setAccessToken([
            'access_token' => $this->cipher->decrypt($connection->getAccessToken()),
            'refresh_token' => $this->cipher->decrypt($connection->getRefreshToken()),
            'expires_in' => max(0, ($connection->getExpiresAt()?->getTimestamp() ?? 0) - time()),
            'created' => time(),
        ]);

        if ($client->isAccessTokenExpired() && null !== ($refresh = $this->cipher->decrypt($connection->getRefreshToken()))) {
            $token = $client->fetchAccessTokenWithRefreshToken($refresh);
            if (isset($token['error'])) {
                throw new \RuntimeException('Google je odbio obnovu pristupa ('.$token['error'].'). Poveži kalendar ponovo.');
            }
            $connection->setAccessToken($this->cipher->encrypt((string) $token['access_token']))
                ->setExpiresAt(new \DateTimeImmutable('+'.(int) ($token['expires_in'] ?? 3600).' seconds'));
            if (!empty($token['refresh_token'])) {
                $connection->setRefreshToken($this->cipher->encrypt((string) $token['refresh_token']));
            }
            $this->em->flush();
        }

        return $client;
    }
}
