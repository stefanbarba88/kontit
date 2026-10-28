<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Service\StatisticsService;
use App\Service\CalendarService;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly StatisticsService $stats,
        private readonly TaskService $tasks,
        private readonly CalendarService $calendar,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $me = $this->getUser();
        \assert($me instanceof User);

        $admin = $this->tasks->isAdmin($me);
        $teamIds = $admin ? $this->stats->allTeamIds() : ($this->tasks->isLeader($me) ? $this->stats->ledTeamIds($me) : []);
        $showTeam = $admin || $this->tasks->isLeader($me);

        return $this->render('dashboard/index.html.twig', [
            'me' => $this->stats->forWorker($me),
            'week' => $this->calendar->week($me),
            'showTeam' => $showTeam,
            'team' => $showTeam ? $this->stats->forTeams($teamIds) : null,
            'admin' => $admin,
            'teams' => $admin ? $this->stats->byTeam() : [],
            'company' => $admin ? $this->stats->forCompany() : null,
            'waitingDays' => StatisticsService::WAITING_DAYS,
        ]);
    }
}
