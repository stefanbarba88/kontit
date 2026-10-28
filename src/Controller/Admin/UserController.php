<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\Role;
use App\Form\UserType;
use App\Repository\ActivityLogRepository;
use App\Repository\ClientRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Service\UserService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
class UserController extends AbstractController
{
    public function __construct(private readonly UserService $users)
    {
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    #[IsGranted('USER_MANAGE')]
    public function index(Request $request, UserRepository $repository, PaginatorInterface $paginator): Response
    {
        $filters = [
            'q' => $request->query->getString('q'),
            'role' => $request->query->getString('role'),
            'status' => $request->query->getString('status'),
        ];

        $pagination = $paginator->paginate(
            $repository->createListQueryBuilder($filters['q'], $filters['role'], $filters['status']),
            $request->query->getInt('page', 1),
            15,
        );

        return $this->render('admin/user/index.html.twig', [
            'pagination' => $pagination,
            'filters' => $filters,
            'roles' => Role::cases(),
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    #[IsGranted('USER_MANAGE')]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'role_choices' => $this->users->assignableRoles($this->getUser()),
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->users->save($user, $form->get('plainPassword')->getData(), $form->get('role')->getData());
                $this->addFlash('success', sprintf('Korisnik %s je kreiran.', $user->getFullName()));

                return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/user/form.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}', name: 'admin_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('USER_VIEW', 'user')]
    public function show(User $user, TeamMemberRepository $members, TeamRepository $teams, ClientRepository $clients, ActivityLogRepository $activity): Response
    {
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'activity' => $activity->findUserPage($user),
            'primaryClients' => $clients->findActivePrimaryFor($user),
            'backupClients' => $clients->findActiveBackupFor($user),
            'currentMembership' => $members->findActiveByUser($user),
            'history' => $members->findHistoryByUser($user),
            'ledTeam' => $teams->findActiveLedBy($user),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('USER_EDIT', 'user')]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, [
            'role_choices' => $this->users->assignableRoles($this->getUser()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->users->save($user, $form->get('plainPassword')->getData(), $form->get('role')->getData());
                $this->addFlash('success', 'Izmene su sačuvane.');

                return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/user/form.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}/toggle', name: 'admin_user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('USER_EDIT', 'user')]
    public function toggle(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('user_toggle_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Nevažeći CSRF token.');
        }

        $activate = !$user->isActive();

        try {
            $this->users->setActive($user, $activate, $this->getUser());
            $this->addFlash('success', $activate ? 'Korisnik je aktiviran.' : 'Korisnik je deaktiviran.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }
}
