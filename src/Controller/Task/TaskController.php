<?php

namespace App\Controller\Task;

use App\Entity\ActivityLog;
use App\Entity\Client;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Form\TaskType;
use App\Repository\ActivityLogRepository;
use App\Repository\ClientRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\TaskCompletionService;
use App\Service\TaskService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskCompletionService $completion,
        private readonly TaskRepository $repository,
        private readonly UserRepository $users,
        private readonly ClientRepository $clients,
    ) {
    }

    #[Route('/tasks', name: 'task_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $me = $this->actor();
        $admin = $this->tasks->isAdmin($me);
        $leader = $this->tasks->isLeader($me);

        $tab = $request->query->getString('tab', 'all');
        $tab = in_array($tab, TaskRepository::TABS, true) ? $tab : 'all';
        $scope = $request->query->getString('scope', 'mine');
        $scope = ('all' === $scope && $leader) ? 'all' : 'mine';
        $client = $request->query->getInt('client') ? $this->clients->find($request->query->getInt('client')) : null;
        $priority = TaskPriority::tryFrom($request->query->getString('priority'));

        if ('approval' === $tab && $leader) {
            $qb = $this->repository->createAwaitingApprovalQueryBuilder($me, $admin);
        } else {
            $tab = 'approval' === $tab ? 'all' : $tab;
            $qb = $this->repository->createListQueryBuilder($me, $admin, $leader, $scope, [
                'tab' => $tab,
                'client' => $client instanceof Client && $this->isGranted('CLIENT_VIEW', $client) ? $client : null,
                'priority' => $priority,
            ]);
        }

        return $this->render('task/index.html.twig', [
            'pagination' => $paginator->paginate($qb, $request->query->getInt('page', 1), 15),
            'filters' => ['tab' => $tab, 'scope' => $scope, 'client' => $client?->getId(), 'priority' => $priority?->value],
            'clients' => $this->clients->createVisibleQueryBuilder($me, $admin, null, 'active')->getQuery()->getResult(),
            'approvalCount' => $leader ? $this->repository->countAwaitingApproval($me, $admin) : 0,
            'canSeeAll' => $leader,
        ]);
    }

    #[Route('/tasks/new', name: 'task_new', methods: ['GET', 'POST'])]
    #[IsGranted('TASK_CREATE')]
    public function new(Request $request): Response
    {
        $me = $this->actor();
        $task = new Task();
        $clientId = $request->query->getInt('client');
        if ($clientId && ($client = $this->clients->find($clientId)) && $this->isGranted('CLIENT_VIEW', $client)) {
            $task->setClient($client);
        }

        $form = $this->createTaskForm($task, $me, [$me]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->tasks->create($task, $this->submittedAssignees($form, $me), $me, $this->wantsEmail($form));
                $this->addFlash('success', sprintf('Zadatak „%s“ je kreiran.', $task->getTitle()));

                return $this->redirectToRoute('task_show', ['id' => $task->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('task/form.html.twig', ['form' => $form, 'task' => $task]);
    }

    #[Route('/tasks/{id}', name: 'task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('TASK_VIEW', 'task')]
    public function show(Task $task, ActivityLogRepository $activity): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'activity' => $activity->findForEntity(ActivityLog::TASK, $task->getId()),
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'task_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('TASK_EDIT', 'task')]
    public function edit(Request $request, Task $task): Response
    {
        $me = $this->actor();
        $form = $this->createTaskForm($task, $me, $task->getAssigneeUsers());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->tasks->update($task, $this->submittedAssignees($form, $me, $task), $me, $this->wantsEmail($form));
                $this->addFlash('success', 'Zadatak je sačuvan.');

                return $this->redirectToRoute('task_show', ['id' => $task->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('task/form.html.twig', ['form' => $form, 'task' => $task]);
    }

    /**
     * Učesnik: počinje rad / "moj deo je gotov" / povlači "moj deo je gotov".
     */
    #[Route('/tasks/{id}/my-part/{action}', name: 'task_my_part', requirements: ['id' => '\d+', 'action' => 'start|done|undo'], methods: ['POST'])]
    #[IsGranted('TASK_COMPLETE', 'task')]
    public function myPart(Request $request, Task $task, string $action): Response
    {
        return $this->perform($request, $task, 'task_my_part_'.$task->getId(), function () use ($request, $task, $action): string {
            $me = $this->actor();
            match ($action) {
                'start' => $this->completion->start($task, $me),
                'done' => $task->hasAssignee($me)
                    ? $this->completion->markDone($task, $me, trim($request->request->getString('note')) ?: null)
                    : $this->completion->markDoneAsSubstitute($task, $me, trim($request->request->getString('note')) ?: null),
                'undo' => $this->completion->undoDone($task, $me),
            };

            return match ($action) {
                'start' => 'Označeno je da radiš na zadatku.',
                'done' => 'Tvoj deo je označen kao gotov.',
                'undo' => 'Tvoj deo je ponovo u radu.',
            };
        });
    }

    #[Route('/tasks/{id}/waiting', name: 'task_waiting', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('TASK_COMPLETE', 'task')]
    public function waiting(Request $request, Task $task): Response
    {
        return $this->perform($request, $task, 'task_waiting_'.$task->getId(), function () use ($request, $task): string {
            $waiting = $request->request->getBoolean('waiting');
            $this->tasks->setWaitingForClient($task, $waiting);

            return $waiting ? 'Zadatak čeka klijenta.' : 'Zadatak je vraćen u rad.';
        });
    }

    #[Route('/tasks/{id}/approve', name: 'task_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('TASK_APPROVE', 'task')]
    public function approve(Request $request, Task $task): Response
    {
        return $this->perform($request, $task, 'task_approve_'.$task->getId(), function () use ($task): string {
            $this->completion->approve($task, $this->actor());

            return 'Zadatak je potvrđen i završen.';
        });
    }

    #[Route('/tasks/{id}/return', name: 'task_return', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('TASK_APPROVE', 'task')]
    public function return(Request $request, Task $task): Response
    {
        return $this->perform($request, $task, 'task_return_'.$task->getId(), function () use ($request, $task): string {
            $this->completion->returnForRework($task, $this->actor(), $request->request->getString('reason'), $request->request->getBoolean('email'));

            return 'Zadatak je vraćen na doradu.';
        });
    }

    #[Route('/tasks/{id}/cancel', name: 'task_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('TASK_CANCEL', 'task')]
    public function cancel(Request $request, Task $task): Response
    {
        return $this->perform($request, $task, 'task_cancel_'.$task->getId(), function () use ($request, $task): string {
            $this->tasks->cancel($task, $request->request->getString('reason'), $this->actor());

            return 'Zadatak je otkazan.';
        });
    }

    /**
     * Zajednički tok POST akcija: CSRF, izvršavanje, flash poruka i povratak na zadatak.
     *
     * @param callable(): string $action vraća poruku o uspehu
     */
    private function perform(Request $request, Task $task, string $tokenId, callable $action): Response
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Neispravan bezbednosni token. Pokušaj ponovo.');
        } else {
            try {
                $this->addFlash('success', $action());
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('task_show', ['id' => $task->getId()]);
    }

    /**
     * @param list<User> $defaultAssignees
     */
    private function createTaskForm(Task $task, User $me, array $defaultAssignees): \Symfony\Component\Form\FormInterface
    {
        $admin = $this->tasks->isAdmin($me);

        $clientQb = $this->clients->createVisibleQueryBuilder($me, $admin, null, 'active');
        if (null !== $task->getClient()) {
            // klijent već na zadatku ostaje u izboru, čak i ako je u međuvremenu deaktiviran
            $clientQb = $this->clients->createVisibleQueryBuilder($me, $admin, null, 'all')
                ->andWhere('c.isActive = true OR c = :current')->setParameter('current', $task->getClient());
        }

        return $this->createForm(TaskType::class, $task, [
            'client_query_builder' => $clientQb,
            'assignee_query_builder' => $this->tasks->isLeader($me)
                ? $this->users->createAssignableQueryBuilder($me, $admin, $task->getAssigneeUsers())
                : null,
            'default_assignees' => $defaultAssignees,
        ]);
    }

    /**
     * @return list<User> bez polja učesnika (radnik) zadatak pripada samo kreatoru, a pri izmeni ostaju postojeći
     */
    private function submittedAssignees(\Symfony\Component\Form\FormInterface $form, User $me, ?Task $existing = null): array
    {
        if ($form->has('assignees')) {
            return array_values($form->get('assignees')->getData() instanceof \Traversable
                ? iterator_to_array($form->get('assignees')->getData(), false)
                : (array) $form->get('assignees')->getData());
        }

        return $existing?->getAssigneeUsers() ?? [$me];
    }

    private function wantsEmail(\Symfony\Component\Form\FormInterface $form): bool
    {
        return $form->has('notifyByEmail') && (bool) $form->get('notifyByEmail')->getData();
    }

    private function actor(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
