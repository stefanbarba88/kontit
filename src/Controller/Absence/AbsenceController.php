<?php

namespace App\Controller\Absence;

use App\Entity\User;
use App\Entity\UserAbsence;
use App\Form\UserAbsenceType;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserRepository;
use App\Service\AbsenceService;
use App\Service\TaskService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AbsenceController extends AbstractController
{
    public function __construct(
        private readonly AbsenceService $service,
        private readonly UserAbsenceRepository $repository,
        private readonly UserRepository $users,
        private readonly TaskService $tasks,
    ) {
    }

    #[Route('/absences', name: 'absence_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $me = $this->actor();
        $admin = $this->tasks->isAdmin($me);
        $leader = $this->tasks->isLeader($me);
        $current = !$request->query->getBoolean('history');

        return $this->render('absence/index.html.twig', [
            'pagination' => $paginator->paginate($this->repository->createListQueryBuilder($me, $admin, $leader, $current), $request->query->getInt('page', 1), 20),
            'pendingAbsences' => $leader ? $this->repository->createPendingQueryBuilder($me, $admin)->getQuery()->getResult() : [],
            'current' => $current,
        ]);
    }

    #[Route('/absences/new', name: 'absence_new', methods: ['GET', 'POST'])]
    #[IsGranted('ABSENCE_CREATE')]
    public function new(Request $request): Response
    {
        $me = $this->actor();
        $leader = $this->tasks->isLeader($me);

        $absence = new UserAbsence();
        $absence->setUser($me);
        $form = $this->createForm(UserAbsenceType::class, $absence, [
            'user_query_builder' => $leader ? $this->users->createAssignableQueryBuilder($me, $this->tasks->isAdmin($me)) : null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->service->create($absence, $me);
                $this->addFlash('success', $absence->getUser() === $me && !$leader
                    ? 'Odsustvo je poslato na odobrenje.'
                    : 'Odsustvo je upisano.');

                return $this->redirectToRoute('absence_index');
            } catch (\DomainException $e) {
                $form->addError(new \Symfony\Component\Form\FormError($e->getMessage()));
            }
        }

        return $this->render('absence/form.html.twig', ['form' => $form, 'needsApproval' => !$leader]);
    }

    #[Route('/absences/{id}/{action}', name: 'absence_action', requirements: ['id' => '\d+', 'action' => 'approve|reject|cancel'], methods: ['POST'])]
    public function action(Request $request, UserAbsence $absence, string $action): Response
    {
        $this->denyAccessUnlessGranted('cancel' === $action ? 'ABSENCE_CANCEL' : 'ABSENCE_DECIDE', $absence);

        if (!$this->isCsrfTokenValid('absence_'.$absence->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Neispravan bezbednosni token. Pokušaj ponovo.');
        } else {
            try {
                $me = $this->actor();
                match ($action) {
                    'approve' => $this->service->approve($absence, $me),
                    'reject' => $this->service->reject($absence, $me),
                    'cancel' => $this->service->cancel($absence, $me),
                };
                $this->addFlash('success', match ($action) {
                    'approve' => 'Odsustvo je odobreno.',
                    'reject' => 'Odsustvo je odbijeno.',
                    'cancel' => 'Odsustvo je otkazano.',
                });
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('absence_index');
    }

    private function actor(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
