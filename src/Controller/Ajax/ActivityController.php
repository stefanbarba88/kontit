<?php

namespace App\Controller\Ajax;

use App\Entity\ActivityLog;
use App\Entity\Client;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ActivityFilter;
use App\Repository\ActivityLogRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Učitaj još" i filteri timeline-a (SPEC 8f.1): vraća HTML fragment strane i da li postoji sledeća.
 */
#[Route('/ajax')]
class ActivityController extends AbstractAjaxController
{
    public function __construct(private readonly ActivityLogRepository $activity)
    {
    }

    #[Route('/users/{id}/activity', name: 'ajax_user_activity', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function user(User $user, Request $request): JsonResponse
    {
        // svako vidi svoju aktivnost (profil), a tuđu ko sme da otvori stranicu korisnika
        if ($user !== $this->getUser()) {
            $this->denyAccessUnlessGranted('USER_VIEW', $user);
        }

        return $this->page($this->activity->findUserPage($user, ActivityFilter::fromRequest($request)), true);
    }

    #[Route('/clients/{id}/activity', name: 'ajax_client_activity', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('CLIENT_VIEW', 'client')]
    public function client(Client $client, Request $request): JsonResponse
    {
        return $this->page($this->activity->findEntityPage(ActivityLog::CLIENT, $client->getId(), ActivityFilter::fromRequest($request)));
    }

    #[Route('/tasks/{id}/activity', name: 'ajax_task_activity', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('TASK_VIEW', 'task')]
    public function task(Task $task, Request $request): JsonResponse
    {
        return $this->page($this->activity->findEntityPage(ActivityLog::TASK, $task->getId(), ActivityFilter::fromRequest($request)));
    }

    /**
     * @param array{entries: list<ActivityLog>, hasMore: bool} $page
     */
    private function page(array $page, bool $showTarget = false): JsonResponse
    {
        return $this->ok([
            'html' => $this->renderView('components/_timeline_items.html.twig', ['entries' => $page['entries'], 'show_target' => $showTarget]),
            'hasMore' => $page['hasMore'],
            'empty' => [] === $page['entries'],
        ]);
    }
}
