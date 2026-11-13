<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\AbsenceOverview;
use App\Service\CalendarService;
use App\Service\IcsExporter;
use App\Service\TaskDueService;
use App\Service\WorkingDayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Kalendar u aplikaciji (SPEC 8n): FullCalendar, događaji AJAX-om za vidljivi opseg, prevlačenje roka zadatka, ICS link.
 */
class CalendarController extends AbstractController
{
    public function __construct(private readonly CalendarService $calendar)
    {
    }

    #[Route('/calendar', name: 'calendar_index', methods: ['GET'])]
    public function index(ClientRepository $clients, AbsenceOverview $overview): Response
    {
        $me = $this->actor();
        $admin = $this->isGranted('ROLE_ADMIN');

        return $this->render('calendar/index.html.twig', [
            'scopes' => $this->calendar->scopesFor($me),
            'clients' => $clients->createVisibleQueryBuilder($me, $admin, null, 'active')->getQuery()->getResult(),
            'people' => $this->isGranted('ROLE_SEF') ? $overview->usersFor($me) : [],
        ]);
    }

    #[Route('/ajax/calendar/events', name: 'ajax_calendar_events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        try {
            $from = new \DateTimeImmutable(substr((string) $request->query->get('start'), 0, 10));
            $to = new \DateTimeImmutable(substr((string) $request->query->get('end'), 0, 10));
        } catch (\Exception) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Neispravan opseg.'], 400);
        }
        if ($to < $from || $to > $from->modify('+100 days')) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Opseg je predug.'], 400);
        }
        $types = array_filter(explode(',', (string) $request->query->get('types', implode(',', CalendarService::TYPES))));

        // FullCalendar traži niz događaja
        return new JsonResponse($this->calendar->events($this->actor(), $from, $to->modify('-1 day'), [
            'scope' => (string) $request->query->get('scope', 'mine'),
            'types' => $types,
            'client' => $request->query->getInt('client') ?: null,
            'user' => $request->query->getInt('user') ?: null,
        ]));
    }

    /**
     * Prevlačenje zadatka na drugi dan menja rok (samo uz TASK_EDIT); vraća upozorenje ako je dan neradni.
     */
    #[Route('/ajax/tasks/{id}/due', name: 'ajax_task_due', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function moveDue(Request $request, Task $task, TaskDueService $due, WorkingDayService $workingDays): JsonResponse
    {
        if (!$this->isGranted('TASK_EDIT', $task)) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Ne možeš da menjaš rok ovog zadatka.'], 403);
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->toArray()['date'] ?? ''));
        if (false === $date) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Neispravan datum.'], 400);
        }
        // ručno pomeren rok važi samo za ovaj zadatak (SPEC 8g.5)
        $due->setDue($task, $date, false, false, null);

        return new JsonResponse(['success' => true, 'data' => ['due' => $date->format('Y-m-d'), 'nonWorking' => $workingDays->reason($date)], 'message' => 'Rok je promenjen na '.$date->format('d.m.Y.').'.']);
    }

    /**
     * ICS pretplata (samo čitanje): tajni link radi bez prijave, sa bilo kojim kalendarom (Google, Outlook, iPhone).
     */
    #[Route('/calendar/ics/{token}.ics', name: 'calendar_ics', requirements: ['token' => '[a-f0-9]{40}'], methods: ['GET'])]
    public function ics(string $token, UserRepository $users, IcsExporter $exporter): Response
    {
        $user = $users->findOneBy(['icsToken' => $token, 'isActive' => true]);
        if (!$user instanceof User) {
            throw $this->createNotFoundException();
        }

        return new Response($exporter->export($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="kontit.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    #[Route('/profile/ics', name: 'profile_ics', methods: ['POST'])]
    #[IsGranted('ROLE_RADNIK')]
    public function icsToken(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('profile_ics', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $me = $this->actor();
        $revoke = 'revoke' === $request->request->getString('action');
        $me->setIcsToken($revoke ? null : bin2hex(random_bytes(20)));
        $em->flush();
        $this->addFlash('success', $revoke ? 'ICS link je poništen.' : 'Napravljen je nov ICS link. Stari više ne radi.');

        return $this->redirectToRoute('app_profile');
    }

    private function actor(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
