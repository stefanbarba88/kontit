<?php

namespace App\Controller;

use App\Entity\JobTemplate;
use App\Entity\JobTemplateChecklistItem;
use App\Entity\User;
use App\Form\JobTemplateType;
use App\Repository\JobCategoryRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vodič kroz dizajn (DESIGN.md 14.4): sve komponente na jednom mestu, za proveru u obe teme.
 * Samo superadmin i samo u dev okruženju.
 */
#[IsGranted('ROLE_SUPERADMIN')]
class DesignController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.environment%')] private readonly string $environment)
    {
    }

    #[Route('/_design', name: 'app_design', methods: ['GET'])]
    public function index(ActivityLogRepository $activity, JobCategoryRepository $categories, \App\Repository\PositionRepository $positions): Response
    {
        if ('dev' !== $this->environment) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        \assert($user instanceof User);

        // primer forme posla (raspored i checklist editor), ne čuva se
        $job = new JobTemplate();
        foreach (['Preuzmi izvode iz banke', 'Proknjiži izvode', 'Uskladi saldo'] as $i => $text) {
            $job->addChecklistItem((new JobTemplateChecklistItem())->setText($text)->setPosition($i));
        }

        return $this->render('design/index.html.twig', [
            'activity' => $activity->findUserPage($user),
            'categories' => $categories->findOrdered(),
            'positions' => $positions->findOrdered(),
            'jobForm' => $this->createForm(JobTemplateType::class, $job)->createView(),
        ]);
    }
}
