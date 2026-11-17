<?php

namespace App\Controller\Absence;

use App\Service\TimeFormat;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Form\UserAbsenceType;
use App\Repository\AbsenceTypeRepository;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserRepository;
use App\Service\AbsenceOverview;
use App\Service\AbsenceService;
use App\Service\LeaveService;
use App\Service\TaskService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Odsustva (SPEC 8j): tabovi Zahtevi (svi), Na odobrenju, Pregled i Evidencija (šef za svoj tim, admin za sve).
 */
class AbsenceController extends AbstractController
{
    private const TABS = ['zahtevi', 'odobrenje', 'pregled', 'evidencija'];

    public function __construct(
        private readonly AbsenceService $service,
        private readonly UserAbsenceRepository $repository,
        private readonly UserRepository $users,
        private readonly TaskService $tasks,
        private readonly LeaveService $leave,
        private readonly AbsenceOverview $overview,
    ) {
    }

    #[Route('/absences', name: 'absence_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $me = $this->actor();
        $admin = $this->tasks->isAdmin($me);
        $leader = $this->tasks->isLeader($me);
        $tab = \in_array($request->query->get('tab'), self::TABS, true) ? $request->query->get('tab') : 'zahtevi';
        if (!$leader) {
            $tab = 'zahtevi';
        }
        $teamId = $request->query->getInt('team') ?: null;
        $year = max(2000, min(2100, $request->query->getInt('year') ?: (int) date('Y')));

        $data = [
            'tab' => $tab,
            'canManage' => $leader,
            'pendingCount' => $leader ? $this->repository->countPendingFor($me, $admin) : 0,
            'teams' => $leader ? $this->overview->teamsFor($me) : [],
            'teamId' => $teamId,
            'year' => $year,
            'balance' => $this->leave->balance($me, (int) date('Y')),
            // radnik vidi samo dostupnost kolega iz tima; šef i admin imaju tab Pregled sa detaljima
            'availability' => $leader ? [] : $this->service->teamAvailability($me),
        ];

        if ('zahtevi' === $tab) {
            $current = !$request->query->getBoolean('history');
            $data['current'] = $current;
            $data['pagination'] = $paginator->paginate($this->repository->createListQueryBuilder($me, $admin, $leader, $current), $request->query->getInt('page', 1), 20);
        } elseif ('odobrenje' === $tab) {
            $data['pendingAbsences'] = $this->repository->createPendingQueryBuilder($me, $admin)->getQuery()->getResult();
        } elseif ('pregled' === $tab) {
            try {
                $month = new \DateTimeImmutable(($request->query->get('month') ?: date('Y-m')).'-01');
            } catch (\Exception) {
                $month = new \DateTimeImmutable('first day of this month');
            }
            $data['month'] = $month;
            $data['grid'] = $this->overview->month($me, $month, $teamId);
        } else {
            $data['records'] = $this->overview->records($me, $year, $teamId);
        }

        return $this->render('absence/index.html.twig', $data);
    }

    #[Route('/absences/new', name: 'absence_new', methods: ['GET', 'POST'])]
    #[IsGranted('ABSENCE_CREATE')]
    public function new(Request $request): Response
    {
        $me = $this->actor();
        $leader = $this->tasks->isLeader($me);

        $absence = (new UserAbsence())->setUser($me);
        if ($request->query->get('from') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query->get('from'))) {
            $absence->setStartDate(new \DateTimeImmutable((string) $request->query->get('from')))->setEndDate(new \DateTimeImmutable((string) $request->query->get('from')));
        }
        $today = $this->tasks->isAdmin($me) ? null : new \DateTimeImmutable('today');
        $form = $this->createForm(UserAbsenceType::class, $absence, [
            'user_query_builder' => $leader ? $this->users->createAssignableQueryBuilder($me, $this->tasks->isAdmin($me)) : null,
            'start_min' => $today,
            'end_min' => $today,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->service->create($absence, $me);
                $this->addFlash('success', $absence->isPending()
                    ? sprintf('Zahtev je poslat na odobrenje. Ako odobravalac ne odgovori do %s, odobrava se automatski.', $absence->getApprovalDeadline()->format(TimeFormat::DATETIME))
                    : 'Odsustvo je upisano.');

                return $this->redirectToRoute('absence_index');
            } catch (\DomainException $e) {
                $form->addError(new \Symfony\Component\Form\FormError($e->getMessage()));
            }
        }

        return $this->render('absence/form.html.twig', ['form' => $form, 'canChooseUser' => $leader, 'me' => $me]);
    }

    /**
     * Izmena odsustva: vrsta, datumi, pola dana, napomena (radnik se ne menja). Prava: ABSENCE_EDIT (AbsenceService::canEdit).
     */
    #[Route('/absences/{id}/edit', name: 'absence_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ABSENCE_EDIT', 'absence')]
    public function edit(Request $request, UserAbsence $absence): Response
    {
        $me = $this->actor();
        $original = [
            'type' => $absence->getType(),
            'startDate' => $absence->getStartDate(),
            'endDate' => $absence->getEndDate(),
            'halfDay' => $absence->isHalfDay(),
            'note' => $absence->getNote(),
        ];
        // ko nije admin, početak odsustva koje već traje može da ostane, ali ništa ne može ranije od današnjeg dana
        $today = $this->tasks->isAdmin($me) ? null : new \DateTimeImmutable('today');
        $form = $this->createForm(UserAbsenceType::class, $absence, [
            'current_type' => $absence->getType(),
            'start_min' => null === $today ? null : min($today, $original['startDate']),
            'end_min' => $today,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->service->update($absence, $me, $original);
                $this->addFlash('success', 'Odsustvo je sačuvano.');

                return $this->redirectToRoute('absence_index');
            } catch (\DomainException $e) {
                $form->addError(new \Symfony\Component\Form\FormError($e->getMessage()));
            }
        }

        return $this->render('absence/form.html.twig', ['form' => $form, 'canChooseUser' => false, 'me' => $me, 'absence' => $absence, 'isAdmin' => $this->tasks->isAdmin($me)]);
    }

    /**
     * Podaci za formu zahteva u letu: radni dani, preostali godišnji, kolege odsutne u istom periodu.
     */
    #[Route('/ajax/absences/preview', name: 'ajax_absence_preview', methods: ['GET'])]
    public function preview(Request $request, AbsenceTypeRepository $types): JsonResponse
    {
        $me = $this->actor();
        // izmena: odsustvo koje se menja se ne računa kao preklapanje ni kao već iskorišćeni dani
        $editing = $request->query->getInt('absence') ? $this->repository->find($request->query->getInt('absence')) : null;
        if (null !== $editing && !$this->isGranted('ABSENCE_EDIT', $editing)) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Nemaš pristup.'], 403);
        }
        $user = $editing?->getUser() ?? ($request->query->getInt('user') ? $this->users->find($request->query->getInt('user')) : $me);
        if (null === $user || (null === $editing && !$this->service->canEnterFor($me, $user))) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Nemaš pristup.'], 403);
        }
        try {
            $from = new \DateTimeImmutable((string) $request->query->get('from'));
            $to = new \DateTimeImmutable((string) ($request->query->get('to') ?: $request->query->get('from')));
        } catch (\Exception) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Neispravan datum.'], 400);
        }
        if ($to > $from->modify('+366 days')) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => 'Period je predug.'], 400);
        }
        $type = $request->query->getInt('type') ? $types->find($request->query->getInt('type')) : null;
        // radnik od kolega vidi samo dostupnost (bez vrste i zahteva na odobrenju), šef i admin detalje
        $preview = $this->service->preview($user, $type, $from, $to, $request->query->getBoolean('half'), $this->tasks->isLeader($me), $editing);
        try {
            $this->service->assertDatesAllowed($me, $from, $to, $editing?->getStartDate());
        } catch (\DomainException $e) {
            if (!\in_array($e->getMessage(), $preview['errors'], true)) {
                array_unshift($preview['errors'], $e->getMessage());
            }
        }
        $preview['approvers'] = null !== $type && !$type->isRequiresApproval() ? [] : array_map(static fn (User $u) => $u->getFullName(), $this->service->approversFor($user));

        return new JsonResponse(['success' => true, 'data' => $preview, 'message' => '']);
    }

    #[Route('/absences/{id}/{action}', name: 'absence_action', requirements: ['id' => '\d+', 'action' => 'approve|reject|cancel|revoke'], methods: ['POST'])]
    public function action(Request $request, UserAbsence $absence, string $action): Response
    {
        $this->denyAccessUnlessGranted(match ($action) { 'cancel' => 'ABSENCE_CANCEL', 'revoke' => 'ABSENCE_REVOKE', default => 'ABSENCE_DECIDE' }, $absence);

        if (!$this->isCsrfTokenValid('absence_'.$absence->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Neispravan bezbednosni token. Pokušaj ponovo.');
        } else {
            try {
                $me = $this->actor();
                match ($action) {
                    'approve' => $this->service->approve($absence, $me),
                    'reject' => $this->service->reject($absence, $me, $request->request->getString('reason')),
                    'cancel' => $this->service->cancel($absence, $me),
                    'revoke' => $this->service->revokeAutoApproval($absence, $me, $request->request->getString('reason')),
                };
                $this->addFlash('success', match ($action) {
                    'approve' => 'Odsustvo je odobreno.',
                    'reject' => 'Odsustvo je odbijeno.',
                    'cancel' => 'Odsustvo je otkazano.',
                    'revoke' => 'Automatsko odobrenje je poništeno.',
                });
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        $back = $request->request->getString('back');

        return str_starts_with($back, '/') && !str_starts_with($back, '//') ? $this->redirect($back) : $this->redirectToRoute('absence_index');
    }

    /**
     * Admin podešava pravo na godišnji odmor i prenete dane za godinu (na stranici korisnika).
     */
    #[Route('/admin/users/{id}/leave', name: 'admin_user_leave', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('USER_MANAGE')]
    public function setLeave(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('leave_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $year = max(2000, min(2100, $request->request->getInt('year') ?: (int) date('Y')));
        $num = static fn (string $v): float => max(0.0, (float) str_replace(',', '.', $v));
        $this->leave->set($user, $year, $num($request->request->getString('entitled')), $num($request->request->getString('carriedOver')));
        $this->addFlash('success', sprintf('Godišnji odmor za %d. je sačuvan.', $year));

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    private function actor(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
