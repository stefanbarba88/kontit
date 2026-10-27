<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Za /ajax/...: proverava CSRF token (header X-CSRF-Token, token id "ajax") na izmenama
 * i pretvara greške pristupa i 404 u JSON, da ih JS može prikazati.
 */
class AjaxSubscriber implements EventSubscriberInterface
{
    public const TOKEN_ID = 'ajax';

    public function __construct(private readonly CsrfTokenManagerInterface $csrf)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 8],
            KernelEvents::EXCEPTION => ['onException', 10],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->isAjax($request) || $request->isMethodSafe()) {
            return;
        }

        $token = (string) $request->headers->get('X-CSRF-Token', '');
        if (!$this->csrf->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            $event->setResponse($this->json('Neispravan bezbednosni token. Osveži stranicu.', 403));
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$this->isAjax($event->getRequest())) {
            return;
        }

        $e = $event->getThrowable();
        if ($e instanceof AccessDeniedException) {
            $event->setResponse($this->json('Nemaš pravo na ovu akciju.', 403));
        } elseif ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            $event->setResponse($this->json(404 === $e->getStatusCode() ? 'Stavka nije pronađena.' : ($e->getMessage() ?: 'Zahtev nije dozvoljen.'), $e->getStatusCode()));
        }
    }

    private function isAjax(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/ajax/');
    }

    private function json(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'data' => null, 'message' => $message], $status);
    }
}
