<?php

namespace App\Controller\Team;

use App\Entity\Team;
use App\Form\TeamMemberType;
use App\Repository\TeamLeaderLogRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TeamController extends AbstractController
{
    /**
     * Šef dolazi na svoj tim. Nema liste timova, jer šef ne vidi druge timove.
     */
    #[Route('/my-team', name: 'my_team', methods: ['GET'])]
    #[IsGranted('ROLE_SEF')]
    public function myTeam(TeamRepository $teams): Response
    {
        $team = $teams->findActiveLedBy($this->getUser());
        if (null === $team) {
            $this->addFlash('warning', 'Trenutno ne vodiš nijedan tim.');

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('team_show', ['id' => $team->getId()]);
    }

    #[Route('/teams/{id}', name: 'team_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('TEAM_MEMBERS_VIEW', 'team')]
    public function show(Team $team, TeamLeaderLogRepository $leaderLogs, \App\Service\TeamService $teams, \App\Repository\TeamMemberRepository $memberRepo): Response
    {
        // šef je član svog tima; ako vodi više timova, član je samo jednog i ovde se prikazuje posebno
        $members = $team->getActiveMembers()->toArray();
        usort($members, static fn ($a, $b) => ($b->getUser() === $team->getLeader()) <=> ($a->getUser() === $team->getLeader()));
        $leaderHome = null;
        if (null !== $team->getLeader() && [] === array_filter($members, static fn ($m) => $m->getUser() === $team->getLeader())) {
            $leaderHome = $memberRepo->findActiveByUser($team->getLeader())?->getTeam();
        }

        $addMemberForm = $this->isGranted('TEAM_MANAGE')
            ? $this->createForm(TeamMemberType::class, null, ['warnings' => $teams->moveWarnings(), 'action' => $this->generateUrl('admin_team_member_add', ['id' => $team->getId()])])
            : null;

        return $this->render('team/show.html.twig', [
            'team' => $team,
            'members' => $members,
            'leaderHome' => $leaderHome,
            'leaderLog' => $leaderLogs->findByTeam($team),
            'addMemberForm' => $addMemberForm,
        ]);
    }
}
