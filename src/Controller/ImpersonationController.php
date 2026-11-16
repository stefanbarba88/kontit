<?php

namespace App\Controller;

use App\Enum\ImpersonationMode;
use App\Service\ImpersonationState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Prebacivanje režima impersonacije: samo pregled / izmene (SPEC 8s). Pokretanje i izlaz idu kroz ugrađeni switch_user (v. ImpersonationSubscriber).
 */
class ImpersonationController extends AbstractController
{
    #[Route('/impersonation/mode', name: 'impersonation_mode', methods: ['POST'])]
    public function mode(Request $request, ImpersonationState $state): RedirectResponse
    {
        if (!$state->isActive()) {
            throw new NotFoundHttpException();
        }
        if (!$this->isCsrfTokenValid('impersonation_mode', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Neispravan bezbednosni token. Osveži stranicu.');

            return $this->back($request);
        }
        $mode = ImpersonationMode::tryFrom((string) $request->request->get('mode')) ?? ImpersonationMode::VIEW;
        $state->setMode($mode);
        $this->addFlash('info', ImpersonationMode::EDIT === $mode
            ? 'Radiš u ime korisnika. Sve što uradiš upisuje se na tvoje ime.'
            : 'Vraćen je režim „samo pregled“.');

        return $this->back($request);
    }

    /**
     * Adresa za pokretanje i izlaz (POST sa `_switch_user` i CSRF tokenom). POST obrađuje ugrađeni switch_user u firewall-u pre kontrolera
     * (ImpersonationSubscriber prvo proverava CSRF), a posle izlaza Symfony vraća na ovu adresu pa GET prosleđuje na stranicu korisnika.
     */
    #[Route('/impersonation/{id}', name: 'impersonation_switch', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function switch(int $id): RedirectResponse
    {
        return $this->redirectToRoute('admin_user_show', ['id' => $id]);
    }

    private function back(Request $request): RedirectResponse
    {
        $referer = (string) $request->headers->get('Referer');
        $url = '' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost().'/') ? $referer : $this->generateUrl('app_dashboard');

        return $this->redirect($url);
    }
}
