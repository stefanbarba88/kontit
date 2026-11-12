<?php

namespace App\Controller\Task;

use App\Entity\User;
use App\Service\TaskListView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Posebne potvrde: pregled zadataka sa posebnom potvrdom za admina i superadmina (SPECIAL_APPROVALS_VIEW).
 */
#[IsGranted('SPECIAL_APPROVALS_VIEW')]
class SpecialApprovalController extends AbstractController
{
    #[Route('/tasks/special-approvals', name: 'special_approvals_index', methods: ['GET'])]
    public function index(Request $request, TaskListView $listView): Response
    {
        $me = $this->getUser();
        \assert($me instanceof User);

        return $this->render('task/special_approvals.html.twig', [
            'list' => $listView->buildSpecial($request, $me, 'special_approvals_index'),
        ]);
    }
}
