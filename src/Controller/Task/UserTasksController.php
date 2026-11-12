<?php

namespace App\Controller\Task;

use App\Entity\User;
use App\Repository\TeamMemberRepository;
use App\Service\TaskListView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tab "Zadaci" korisnika (/users/{id}/zadaci): sam korisnik (svoje), šef za radnike svog tima, admin+ za sve; ostali dobijaju 403 (USER_TASKS_VIEW).
 * Lista je ista komponenta kao /tasks i tab klijenta; "Zaduženi" je zaključan na ovog korisnika, a uz to postoji filter "Uloga".
 * U listi se vide samo zadaci koje ulogovani korisnik sme da vidi (TaskVoter / TaskRepository::createFilteredQueryBuilder).
 */
class UserTasksController extends AbstractController
{
    #[Route('/users/{id}/zadaci', name: 'user_tasks', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('USER_TASKS_VIEW', 'user')]
    public function __invoke(Request $request, User $user, TaskListView $taskList, TeamMemberRepository $members): Response
    {
        $me = $this->getUser();
        \assert($me instanceof User);

        return $this->render('task/user_tasks.html.twig', [
            'user' => $user,
            'team' => $members->findActiveByUser($user)?->getTeam(),
            'list' => $taskList->build($request, $me, 'user_tasks', ['id' => $user->getId()], null, $user),
        ]);
    }
}
