<?php

namespace App\Controller\Dashboard;

use App\Controller\Ajax\MatrixController;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\SpecialApprovalService;
use App\Service\StatisticsService;
use App\Service\CalendarService;
use App\Service\ClientJobMatrixService;
use App\Service\ImpersonationState;
use App\Service\TaskService;
use App\Service\TodoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly StatisticsService $stats,
        private readonly TaskService $tasks,
        private readonly CalendarService $calendar,
        private readonly TaskRepository $taskRepository,
        private readonly SpecialApprovalService $specialApprovals,
        private readonly ClientJobMatrixService $matrix,
        private readonly TodoService $todo,
        private readonly ImpersonationState $impersonation,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(Request $request): Response
    {
        $me = $this->getUser();
        \assert($me instanceof User);

        $admin = $this->tasks->isAdmin($me);
        $teamIds = $admin ? $this->stats->allTeamIds() : ($this->tasks->isLeader($me) ? $this->stats->ledTeamIds($me) : []);
        $showTeam = $admin || $this->tasks->isLeader($me);

        $special = null;
        if ($this->isGranted('SPECIAL_APPROVALS_VIEW')) {
            $oldest = $this->taskRepository->oldestAwaitingSpecialApproval($me, false);
            $special = [
                'count' => $this->taskRepository->countAwaitingSpecialApproval($me, false),
                'oldest' => $oldest,
                'overdue' => null !== $oldest && $this->specialApprovals->waitingWorkingDaysSince($oldest) > SpecialApprovalService::REMIND_AFTER_WORKING_DAYS,
            ];
        }

        // Pregled poslova po klijentima (SPEC 8r): filteri iz URL-a, prvih 15 klijenata
        $matrixFilters = $this->matrix->filters($request->query->all());

        return $this->render('dashboard/index.html.twig', [
            'mx' => $this->matrix->build($matrixFilters, $me, 1, MatrixController::PER_PAGE_BLOCK),
            'full' => false,
            'periodOptions' => $this->matrix->periodOptions($matrixFilters),
            'periodLabel' => $this->matrix->periodLabel($matrixFilters),
            'special' => $special,
            'noReview' => $admin ? $this->stats->noReviewDecisions() : [],
            'me' => $this->stats->forWorker($me),
            'week' => $this->calendar->week($me),
            'showTeam' => $showTeam,
            // admin: cela firma (i radnici bez tima i šefovi), šef: timovi koje vodi
            'team' => $showTeam ? $this->stats->forTeams($teamIds, $admin) : null,
            'admin' => $admin,
            'teams' => $admin ? $this->stats->byTeam() : [],
            'company' => $admin ? $this->stats->forCompany() : null,
            'waitingDays' => StatisticsService::WAITING_DAYS,
            // Podsetnik je privatan: dok traje "Prikaži kao korisnika" kartice nema (SPEC 8p, 8s)
            'todoCompact' => $this->impersonation->isActive() ? null : $this->todo->compact($me),
        ]);
    }
}
