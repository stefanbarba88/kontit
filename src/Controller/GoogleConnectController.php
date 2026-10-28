<?php

namespace App\Controller;

use App\Entity\GoogleCalendarConnection;
use App\Entity\User;
use App\Google\CalendarGatewayInterface;
use App\Google\CalendarSync;
use App\Google\GoogleCalendarGateway;
use App\Google\TokenCipher;
use App\Message\SyncUserCalendar;
use App\Repository\GoogleCalendarConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Povezivanje Google kalendara (SPEC 8n.3): svaki korisnik sam povezuje svoj nalog (OAuth 2.0), aplikacija pravi kalendar
 * "KontIT" i u njega šalje samo njegove događaje. Odvezivanje jednim klikom.
 */
#[IsGranted('ROLE_RADNIK')]
class GoogleConnectController extends AbstractController
{
    public function __construct(
        private readonly CalendarSync $sync,
        private readonly CalendarGatewayInterface $gateway,
    ) {
    }

    #[Route('/connect/google', name: 'connect_google', methods: ['GET'])]
    public function connect(ClientRegistry $clients): Response
    {
        if (!$this->sync->isActive()) {
            $this->addFlash('warning', 'Povezivanje sa Google kalendarom nije uključeno (administrator treba da podesi Google pristup).');

            return $this->redirectToRoute('app_profile');
        }

        // prompt=consent: Google tada uvek vraća refresh token
        return $clients->getClient('google')->redirect(GoogleCalendarGateway::SCOPES, ['prompt' => 'consent', 'access_type' => 'offline']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function check(Request $request, ClientRegistry $clients, TokenCipher $cipher, GoogleCalendarConnectionRepository $connections, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        $me = $this->getUser();
        \assert($me instanceof User);
        if ($request->query->has('error')) {
            $this->addFlash('warning', 'Povezivanje je otkazano u Google-u.');

            return $this->redirectToRoute('app_profile');
        }

        try {
            $client = $clients->getClient('google');
            $token = $client->getAccessToken();
            $owner = $client->fetchUserFromToken($token);

            $connection = $connections->findOneBy(['user' => $me]) ?? new GoogleCalendarConnection($me, $cipher->encrypt($token->getToken()));
            $connection->setAccessToken($cipher->encrypt($token->getToken()))
                ->setExpiresAt(null !== $token->getExpires() ? (new \DateTimeImmutable())->setTimestamp($token->getExpires()) : null)
                ->setGoogleEmail(method_exists($owner, 'getEmail') ? $owner->getEmail() : null);
            if (null !== $token->getRefreshToken()) {
                $connection->setRefreshToken($cipher->encrypt($token->getRefreshToken()));
            }
            $em->persist($connection);
            $em->flush();

            if (null === $connection->getCalendarId()) {
                $connection->setCalendarId($this->gateway->createCalendar($connection));
                $em->flush();
            }
            $bus->dispatch(new SyncUserCalendar((int) $me->getId()));
            $this->addFlash('success', sprintf('Google kalendar je povezan (%s). Događaji se upisuju u kalendar „KontIT“.', $connection->getGoogleEmail() ?? 'Google nalog'));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Povezivanje nije uspelo: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/google/disconnect', name: 'profile_google_disconnect', methods: ['POST'])]
    public function disconnect(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('google_disconnect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $me = $this->getUser();
        \assert($me instanceof User);
        $this->sync->disconnect($me);
        $this->addFlash('success', 'Google kalendar je odvezan. Kalendar „KontIT“ je ostao u tvom Google nalogu i možeš da ga obrišeš.');

        return $this->redirectToRoute('app_profile');
    }
}
