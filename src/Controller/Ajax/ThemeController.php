<?php

namespace App\Controller\Ajax;

use App\Enum\ThemePreference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Čuva izbor teme (svetla, tamna, kao sistem) da prati korisnika na svakom računaru.
 */
class ThemeController extends AbstractAjaxController
{
    #[Route('/ajax/theme', name: 'ajax_theme', methods: ['POST'])]
    public function set(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $preference = ThemePreference::tryFrom(strtoupper((string) ($this->payload($request)['theme'] ?? '')));
        if (null === $preference) {
            return $this->fail('Nepoznata tema.');
        }

        $this->actor()->setThemePreference($preference);
        $em->flush();

        return $this->ok(['theme' => strtolower($preference->value)]);
    }
}
