<?php

namespace App\Controller\Ajax;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AJAX rute (/ajax/...) vraćaju { success, data, message }. CSRF (header X-CSRF-Token) proverava AjaxSubscriber.
 */
abstract class AbstractAjaxController extends AbstractController
{
    protected function ok(mixed $data = null, string $message = ''): JsonResponse
    {
        return new JsonResponse(['success' => true, 'data' => $data, 'message' => $message]);
    }

    protected function fail(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['success' => false, 'data' => null, 'message' => $message], $status);
    }

    /**
     * Telo zahteva kao JSON niz (prazan niz ako telo nije ispravan JSON).
     *
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function actor(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
