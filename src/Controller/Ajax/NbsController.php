<?php

namespace App\Controller\Ajax;

use App\Repository\ClientRepository;
use App\Service\NbsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NbsController extends AbstractAjaxController
{
    /**
     * Podaci firme iz NBS-a za popunjavanje forme klijenta. Odbija PIB koji već postoji.
     */
    #[Route('/ajax/nbs', name: 'ajax_nbs', methods: ['GET'])]
    #[IsGranted('CLIENT_CREATE')]
    public function lookup(Request $request, NbsService $nbs, ClientRepository $clients): JsonResponse
    {
        $pib = trim($request->query->getString('pib'));
        $existing = preg_match('/^\d{9}$/', $pib) ? $clients->findOneBy(['pib' => $pib]) : null;
        if (null !== $existing && $existing->getId() !== $request->query->getInt('exclude_id')) {
            return $this->fail('Klijent sa tim PIB-om već postoji: '.$existing->getName().'.', 409);
        }

        try {
            $data = $nbs->lookup($pib);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable) {
            return $this->fail('Greška pri pozivu NBS servisa.', 502);
        }

        // datumi kao tekst za <input type="date">
        foreach (['registrationDate', 'foundingDate'] as $key) {
            $data[$key] = $data[$key]?->format('Y-m-d');
        }

        return $this->ok($data);
    }
}
