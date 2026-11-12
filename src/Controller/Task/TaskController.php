<?php

namespace App\Controller\Task;

use App\Entity\ActivityLog;
use App\Entity\ChecklistItem;
use App\Entity\Task;
use App\Entity\User;
use App\Form\TaskType;
use App\Repository\ActivityLogRepository;
use App\Repository\ClientJobRepository;
use App\Repository\ClientRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\ClientPeople;
use App\Enum\ReviewMode;
use App\Service\ReturnUrlResolver;
use App\Service\ReviewChoice;
use App\Service\TaskReviewPolicy;
use App\Service\TaskCompletionService;
use App\Service\TaskListView;
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
        private readonly ClientJobRepository $jobs,
        private readonly ClientPeople $clientPeople,
        private readonly ReturnUrlResolver $returnUrl,
        private readonly TaskReviewPolicy $reviewPolicy,
        private readonly \App\Repository\TodoItemRepository $todoItems,
        private readonly \App\Service\TodoService $todo,
    ) {
    }

    #[Route('/tasks', name: 'task_index', methods: ['GET'])]
    public function index(Request $request, TaskListView $listView): Response
    {
        return $this->render('task/index.html.twig', [
            'list' => $listView->build($request, $this->actor(), 'task_index', []),
        ]);
    }

    /**
     * Novi zadatak (SPEC 8i.2, 8i.3). Sa kartice klijenta (/clients/{id}/tasks/new) klijent je zaključan;
     * ?job= (iz "Kreiraj zadatak sada") popunjava naslov, checklist, prioritet i procenu iz ugovorenog posla.
     */
    #[Route('/tasks/new', name: 'task_new', methods: ['GET', 'POST'])]
    #[Route('/clients/{clientId}/tasks/new', name: 'client_task_new', requirements: ['clientId' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('TASK_CREATE')]
    public function new(Request $request, int $clientId = 0): Response
    {
        $me = $this->actor();
        $task = new Task();
        $locked = $clientId > 0;
        $clientId = $clientId ?: $request->query->getInt('client');
        if ($clientId) {
            $client = $this->clients->find($clientId);
            if (null === $client || !$this->isGranted('CLIENT_VIEW', $client)) {
                throw $this->createNotFoundException();
            }
            $task->setClient($client);
        }
        // klik na prazan dan u kalendaru: ?due=YYYY-MM-DD
        if (!$request->isMethod('POST') && false !== ($due = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('due')))) {
            $task->setDueDate($due);
        }
        // "Pretvori u zadatak" iz Moje liste: ?fromTodo=ID. Samo VLASNIK stavke (tuđa ili nepostojeća daje 404, i za admina)
        $fromTodo = null;
        if ($request->query->getInt('fromTodo')) {
            $fromTodo = $this->todoItems->findOwned($request->query->getInt('fromTodo'), $me);
            if (null === $fromTodo || !$this->isGranted('TODO_VIEW', $fromTodo)) {
                throw $this->createNotFoundException();
            }
            if (null === $task->getClient() && null !== $fromTodo->getClient() && $this->isGranted('CLIENT_VIEW', $fromTodo->getClient())) {
                $task->setClient($fromTodo->getClient());
            }
            if (!$request->isMethod('POST')) {
                $task->setTitle($fromTodo->getTitle())->setDescription($fromTodo->getNote());
                $fields = $this->todo->formValues($fromTodo);
                if ('' !== $fields['dueDate'] && false !== ($due = \DateTimeImmutable::createFromFormat('!Y-m-d', $fields['dueDate']))) {
                    $task->setDueDate($due);
                }
            }
        }
        $job = null;
        if ($request->query->getInt('job') && null !== $task->getClient()) {
            $job = $this->jobs->find($request->query->getInt('job'));
            if (null === $job || $job->getClient() !== $task->getClient()) {
                $job = null;
            }
        }
        if (null !== $job && !$request->isMethod('POST')) {
            $template = $job->getJobTemplate();
            $task->setTitle(sprintf('%s — %s', $template->getName(), $task->getClient()->getShortName()))
                ->setDescription($template->getDescription())
                ->setPriority($template->getDefaultPriority())
                ->setCompletionMode($template->getCompletionMode())
                ->linkJob($job);
        }

        // podrazumevani učesnik: glavni radnik klijenta (ili zaduženi na poslu), ako sme da mu se dodeli; inače ja
        $default = $job?->getAssigneeOverride() ?? $task->getClient()?->getPrimaryAccountant();
        $returnTo = $this->returnUrl->fromRequest($request);
        $form = $this->createTaskForm($task, $me, null !== $default && $this->tasks->canAssignTo($me, $default) ? [$default] : [$me], $locked);
        if (null !== $job && !$request->isMethod('POST') && $form->has('checklist')) {
            $form->get('checklist')->setData(array_map(static fn ($i) => ['text' => $i->getText(), 'position' => $i->getPosition()], $job->getJobTemplate()->getChecklistItems()->toArray()));
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                foreach (array_values((array) $form->get('checklist')->getData()) as $position => $row) {
                    $task->addChecklistItem(new ChecklistItem($task, (string) $row['text'], $position));
                }
                $this->tasks->create($task, $this->submittedAssignees($form, $me), $me, $this->wantsEmail($form), false, $this->reviewChoice($form));
                $this->addFlash('success', sprintf('Zadatak „%s“ je kreiran.', $task->getTitle()));
                if (null !== $fromTodo) {
                    $this->todo->markConverted($fromTodo, $task);
                }

                return $this->redirect($this->returnUrl->resolve($returnTo, $this->generateUrl('task_show', ['id' => $task->getId()])));
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('task/form.html.twig', [
            'form' => $form,
            'task' => $task,
            'locked' => $locked,
            'returnTo' => $returnTo,
            'people' => null !== $task->getClient() ? $this->clientPeople->of($task->getClient()) : [],
        ]);
    }

    #[Route('/tasks/{id}', name: 'task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('TASK_VIEW', 'task')]
    public function show(Task $task, ActivityLogRepository $activity): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'activity' => $activity->findEntityPage(ActivityLog::TASK, $task->getId()),
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'task_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('TASK_EDIT', 'task')]
    public function edit(Request $request, Task $task): Response
    {
        $me = $this->actor();
        $returnTo = $this->returnUrl->fromRequest($request);
        $form = $this->createTaskForm($task, $me, $task->getAssigneeUsers());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->tasks->update($task, $this->submittedAssignees($form, $me, $task), $me, $this->wantsEmail($form), $this->reviewChoice($form));
                $this->addFlash('success', 'Zadatak je sačuvan.');

                return $this->redirect($this->returnUrl->resolve($returnTo, $this->generateUrl('task_show', ['id' => $task->getId()])));
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('task/form.html.twig', ['form' => $form, 'task' => $task, 'locked' => false, 'returnTo' => $returnTo, 'people' => null !== $task->getClient() ? $this->clientPeople->of($task->getClient()) : []]);
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

        // akcije iz liste (Posebne potvrde) vraćaju na listu sa filterima
        return $this->redirect($this->returnUrl->resolve($this->returnUrl->fromRequest($request), $this->generateUrl('task_show', ['id' => $task->getId()])));
    }

    /**
     * @param list<User> $defaultAssignees
     */
    private function createTaskForm(Task $task, User $me, array $defaultAssignees, bool $lockClient = false): \Symfony\Component\Form\FormInterface
    {
        $admin = $this->tasks->isAdmin($me);

        $clientQb = $this->clients->createVisibleQueryBuilder($me, $admin, null, 'active');
        if (null !== $task->getClient()) {
            // klijent već na zadatku ostaje u izboru, čak i ako je u međuvremenu deaktiviran
            $clientQb = $this->clients->createVisibleQueryBuilder($me, $admin, null, 'all')
                ->andWhere('c.isActive = true OR c = :current')->setParameter('current', $task->getClient());
        }

        // posao: ugovoreni poslovi klijenata koje korisnik vidi (JS ih filtrira po izabranom klijentu)
        $clientIds = array_map('intval', array_column((clone $clientQb)->select('c.id')->resetDQLPart('orderBy')->getQuery()->getArrayResult(), 'id'));

        return $this->createForm(TaskType::class, $task, [
            'client_query_builder' => $clientQb,
            'job_query_builder' => $this->jobs->createContractedQueryBuilder($clientIds, $task->getClientJob()),
            'lock_client' => $lockClient,
            'assignee_query_builder' => $this->tasks->isLeader($me)
                ? $this->users->createAssignableQueryBuilder($me, $admin, $task->getAssigneeUsers())
                : null,
            'default_assignees' => $defaultAssignees,
            'review_modes' => $this->reviewModesFor($task, $me),
            'review_approver_query_builder' => $this->users->createReviewApproverQueryBuilder($task->getRequiredApprovers()),
        ]);
    }

    /**
     * Izbor "Provera" u formi: skriven za kreatora čija pozicija traži proveru, za zadatak iz posla koji traži potvrdu (zaključano)
     * i za izmenu koju akter ne sme. Koji je izbor dozvoljen za izabrane učesnike proverava server (TaskReviewPolicy::modesFor).
     *
     * @return list<ReviewMode>
     */
    private function reviewModesFor(Task $task, User $me): array
    {
        if ($me->getPosition()?->requiresTaskReview()) {
            return [];
        }
        if (null !== $task->getId() && !$this->reviewPolicy->canChangeMode($task, $me)) {
            return [];
        }
        $template = $task->getClientJob()?->getJobTemplate();
        if ($task->isReviewFromJob() || (null === $task->getId() && null !== $template && $template->requiresApproval())) {
            return [];
        }

        return $this->tasks->isLeader($me) ? [ReviewMode::AUTO, ReviewMode::NONE, ReviewMode::SPECIAL] : [ReviewMode::AUTO, ReviewMode::SPECIAL];
    }

    private function reviewChoice(\Symfony\Component\Form\FormInterface $form): ?ReviewChoice
    {
        if (!$form->has('reviewMode')) {
            return null;
        }
        $mode = $form->get('reviewMode')->getData();

        return $mode instanceof ReviewMode ? new ReviewChoice($mode, array_values((array) ($form->get('reviewApprovers')->getData() instanceof \Traversable ? iterator_to_array($form->get('reviewApprovers')->getData(), false) : $form->get('reviewApprovers')->getData()))) : null;
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
