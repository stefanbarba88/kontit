<?php

namespace App\Controller\Ajax;

use App\Entity\Client;
use App\Repository\ClientJobRepository;
use App\Service\ClientPeople;
use App\Service\ScheduleCalculator;
use App\Service\ScheduleResolver;
use App\Service\TaskService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Forma zadatka (SPEC 8i.3): po izboru klijenta njegovi ugovoreni poslovi (sa checklistom, prioritetom i procenom)
 * i ljudi raspoređeni na njega, ograničeni na one kojima korisnik sme da dodeli zadatak (SPEC 4.4).
 */
class TaskOptionsController extends AbstractAjaxController
{
    #[Route('/ajax/clients/{id}/task-options', name: 'ajax_client_task_options', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('CLIENT_VIEW', 'client')]
    public function options(Client $client, ClientJobRepository $jobs, ClientPeople $people, ScheduleCalculator $calculator, ScheduleResolver $resolver, TaskService $tasks): JsonResponse
    {
        $me = $this->actor();
        $jobList = [];
        foreach ($jobs->findContracted($client) as $job) {
            $template = $job->getJobTemplate();
            $jobList[] = [
                'id' => $job->getId(),
                'name' => $template->getName(),
                'describe' => $calculator->describe($resolver->forClientJob($job), true),
                'title' => sprintf('%s — %s', $template->getName(), $client->getShortName()),
                'priority' => $template->getDefaultPriority()->value,
                'completionMode' => $template->getCompletionMode()->value,
                'estimatedMinutes' => $job->getEstimatedMinutes(),
                'assignee' => $job->getAssigneeOverride()?->getId(),
                'checklist' => array_values(array_map(static fn ($i) => (string) $i->getText(), $template->getChecklistItems()->toArray())),
            ];
        }

        $assignees = [];
        foreach ($people->of($client) as $person) {
            if ($tasks->canAssignTo($me, $person['user'])) {
                $assignees[] = ['id' => $person['user']->getId(), 'name' => $person['user']->getFullName(), 'role' => $person['role']];
            }
        }

        return $this->ok([
            'client' => ['id' => $client->getId(), 'shortName' => $client->getShortName(), 'pib' => $client->getPib(), 'primary' => $client->getPrimaryAccountant()?->getId()],
            'jobs' => $jobList,
            'assignees' => $assignees,
        ]);
    }
}
