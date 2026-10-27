<?php

namespace App\Controller\Ajax;

use App\Nbs\NbsCompanyLookupService;
use App\Repository\ClientRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NbsController extends AbstractAjaxController
{
    /**
     * Podaci firme iz NBS-a za popunjavanje forme klijenta: naziv, adresa, mesto, PIB, matični broj, tekući računi.
     * Odbija neispravan PIB (kontrolna cifra) i PIB koji već postoji kod drugog klijenta.
     */
    #[Route('/ajax/client/lookup', name: 'ajax_client_lookup', methods: ['GET'])]
    #[IsGranted('CLIENT_CREATE')]
    public function lookup(Request $request, NbsCompanyLookupService $nbs, ClientRepository $clients): JsonResponse
    {
        $pib = trim($request->query->getString('pib'));
        $existing = preg_match('/^\d{9}$/', $pib) ? $clients->findOneBy(['pib' => $pib]) : null;
        if (null !== $existing && $existing->getId() !== $request->query->getInt('exclude_id')) {
            return $this->fail('Klijent sa tim PIB-om već postoji: '.$existing->getName().'.', 409);
        }

        try {
            $data = $nbs->lookup($pib, 'user_'.$this->actor()->getId());
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Throwable) {
            return $this->fail('NBS trenutno ne odgovara, unesi podatke ručno.', 502);
        }

        // datumi kao tekst za <input type="date">
        foreach (['registrationDate', 'foundingDate'] as $key) {
            $data[$key] = $data[$key]?->format('Y-m-d');
        }

        return $this->ok($data);
    }
}
