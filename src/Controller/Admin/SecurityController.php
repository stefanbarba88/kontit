<?php

namespace App\Controller\Admin;

use App\Repository\ImpersonationLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Podešavanja → Sigurnost: evidencija "Prikaži kao korisnik" (samo superadmin, SPEC 8s). */
#[IsGranted('IMPERSONATION_LOG_VIEW')]
class SecurityController extends AbstractController
{
    #[Route('/admin/security', name: 'admin_security_index', methods: ['GET'])]
    public function index(ImpersonationLogRepository $logs): Response
    {
        return $this->render('admin/security/index.html.twig', ['logs' => $logs->findRecent(200)]);
    }
}
