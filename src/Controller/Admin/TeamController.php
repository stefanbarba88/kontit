<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Form\TeamMemberType;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use App\Service\TeamService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/teams')]
#[IsGranted('TEAM_MANAGE')]
class TeamController extends AbstractController
{
    public function __construct(private readonly TeamService $teams)
    {
    }

    #[Route('', name: 'admin_team_index', methods: ['GET'])]
    public function index(Request $request, TeamRepository $repository, PaginatorInterface $paginator): Response
    {
        $pagination = $paginator->paginate($repository->createListQueryBuilder(), $request->query->getInt('page', 1), 15);

        return $this->render('admin/team/index.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/new', name: 'admin_team_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->teams->create($team, $form->get('leader')->getData(), $this->getUser());
                $this->addFlash('success', sprintf('Tim „%s“ je kreiran.', $team->getName()));

                return $this->redirectToRoute('team_show', ['id' => $team->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/team/form.html.twig', ['form' => $form, 'team' => $team]);
    }

    #[Route('/{id}/edit', name: 'admin_team_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Team $team): Response
    {
        $this->denyIfDeleted($team);

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->teams->update($team, $form->get('leader')->getData(), $this->getUser());
                $this->addFlash('success', 'Izmene su sačuvane.');

                return $this->redirectToRoute('team_show', ['id' => $team->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/team/form.html.twig', ['form' => $form, 'team' => $team]);
    }

    #[Route('/{id}/delete', name: 'admin_team_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Team $team): Response
    {
        $this->denyIfDeleted($team);
        $this->assertCsrf($request, 'team_delete_'.$team->getId());

        try {
            $this->teams->delete($team);
            $this->addFlash('success', sprintf('Tim „%s“ je obrisan.', $team->getName()));

            return $this->redirectToRoute('admin_team_index');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('team_show', ['id' => $team->getId()]);
        }
    }

    #[Route('/{id}/members', name: 'admin_team_member_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addMember(Request $request, Team $team): Response
    {
        $this->denyIfDeleted($team);

        $form = $this->createForm(TeamMemberType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $form->get('user')->getData();
                $previous = $this->teams->addMember($team, $user, $this->getUser());
                $this->addFlash('success', null === $previous
                    ? sprintf('%s je dodat u tim.', $user->getFullName())
                    : sprintf('%s je premešten iz tima „%s“.', $user->getFullName(), $previous->getName()));
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Izaberi radnika.');
        }

        return $this->redirectToRoute('team_show', ['id' => $team->getId()]);
    }

    #[Route('/members/{id}/remove', name: 'admin_team_member_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function removeMember(Request $request, TeamMember $member): Response
    {
        $team = $member->getTeam();
        $this->denyIfDeleted($team);
        $this->assertCsrf($request, 'member_remove_'.$member->getId());

        try {
            $this->teams->removeMember($member);
            $this->addFlash('success', sprintf('%s je uklonjen iz tima.', $member->getUser()->getFullName()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('team_show', ['id' => $team->getId()]);
    }

    private function denyIfDeleted(Team $team): void
    {
        if ($team->isDeleted()) {
            throw $this->createNotFoundException();
        }
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Nevažeći CSRF token.');
        }
    }
}
