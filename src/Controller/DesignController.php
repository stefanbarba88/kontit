<?php

namespace App\Controller;

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
    public function index(): Response
    {
        if ('dev' !== $this->environment) {
            throw $this->createNotFoundException();
        }

        return $this->render('design/index.html.twig');
    }
}
