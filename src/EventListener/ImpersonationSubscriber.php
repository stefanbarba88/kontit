<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\ImpersonationEnd;
use App\Service\ImpersonationState;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * "Prikaži kao korisnik" (SPEC 8s), sva serverska pravila na JEDNOM mestu (ne po kontrolerima):
 *  - POST + CSRF za pokretanje i izlaz (ugrađeni GET ?_switch_user= je izložen CSRF-u pa se odbija),
 *  - evidencija početka, kraja (izlaz, istek, odjava) i aktivnosti,
 *  - automatski izlaz posle neaktivnosti (parametar impersonation.idle_minutes),
 *  - Podsetnik je za vreme impersonacije 404 (strogo privatan),
 *  - "samo pregled": sve izmene (POST/PUT/PATCH/DELETE, i AJAX) su blokirane,
 *  - u režimu izmena važi lista zabranjenih radnji (ImpersonationRules).
 */
final class ImpersonationSubscriber implements EventSubscriberInterface
{
    public const TOKEN_ID = 'impersonate';
    public const PARAM = '_switch_user';

    /** Pozadinski pozivi koji ne računaju kao aktivnost korisnika (inače zvono drži sesiju večno otvorenom). */
    private const PASSIVE_ROUTES = ['ajax_notifications'];

    /** Uvek dozvoljeno, i u režimu "samo pregled". */
    private const ALWAYS_ALLOWED = ['impersonation_mode', 'app_logout'];

    public function __construct(
        private readonly ImpersonationState $state,
        private readonly TokenStorageInterface $tokens,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly UrlGeneratorInterface $urls,
        private readonly ImpersonationRules $rules,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // iznad firewall-a (8): tek posle ovih provera ugrađeni switch_user sme da radi
            KernelEvents::REQUEST => [['hardenSwitch', 9], ['guard', 5]],
            SecurityEvents::SWITCH_USER => 'onSwitch',
            LogoutEvent::class => ['onLogout', 10],
        ];
    }

    public function hardenSwitch(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()) {
            return;
        }
        $inQuery = $request->query->has(self::PARAM) || $request->headers->has(self::PARAM);
        $inBody = $request->request->has(self::PARAM);
        if (!$inQuery && !$inBody) {
            return;
        }
        // nema ugnježdavanja ni "preskakanja" na drugog korisnika: prvo Izađi (jedino _exit je dozvoljen dok impersonacija traje)
        $nested = $request->hasSession() && $this->state->isRunning($request->getSession()) && '_exit' !== (string) $request->request->get(self::PARAM);
        $valid = !$inQuery && !$nested && $request->isMethod('POST')
            && $this->csrf->isTokenValid(new CsrfToken(self::TOKEN_ID, (string) $request->request->get('_token', '')));
        if (!$valid) {
            $event->setResponse(new Response('Nedozvoljen zahtev.', Response::HTTP_FORBIDDEN));
        }
    }

    public function guard(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !($this->tokens->getToken() instanceof SwitchUserToken)) {
            return;
        }
        $route = (string) $request->attributes->get('_route', '');
        if ('app_logout' === $route) {
            return;
        }

        if ($this->state->isExpired()) {
            $event->setResponse($this->expire($request));

            return;
        }
        if (!\in_array($route, self::PASSIVE_ROUTES, true)) {
            $this->state->touch();
        }

        // Podsetnik je strogo privatan: za vreme impersonacije ne postoji (404, kao tuđa stavka)
        if ($this->rules->isPrivate($route)) {
            throw new NotFoundHttpException();
        }

        if (\in_array($route, self::ALWAYS_ALLOWED, true)) {
            return;
        }
        if (!$request->isMethodSafe() || $this->rules->forbidsRead($route)) {
            $message = $this->rules->denial($route, $request, $this->state->isEditing());
            if (null !== $message) {
                $event->setResponse($this->deny($request, $message));
            }
        }
    }

    public function onSwitch(SwitchUserEvent $event): void
    {
        $token = $event->getToken();
        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($token instanceof SwitchUserToken) {
            $actor = $token->getOriginalToken()->getUser();
            $target = $event->getTargetUser();
            if ($actor instanceof User && $target instanceof User) {
                $this->state->start($actor, $target, $request->getClientIp(), $session);
            }

            return;
        }
        $this->state->end(ImpersonationEnd::MANUAL, $session);
    }

    public function onLogout(LogoutEvent $event): void
    {
        if ($event->getToken() instanceof SwitchUserToken) {
            $request = $event->getRequest();
            $this->state->end(ImpersonationEnd::LOGOUT, $request->hasSession() ? $request->getSession() : null);
        }
    }

    private function expire(Request $request): Response
    {
        $token = $this->tokens->getToken();
        $target = $this->state->target();
        $minutes = $this->state->idleMinutes();
        $this->state->end(ImpersonationEnd::TIMEOUT);
        if ($token instanceof SwitchUserToken) {
            $this->tokens->setToken($token->getOriginalToken());
        }
        $this->flash($request, 'warning', sprintf('Pregled tuđeg naloga je prekinut posle %s min neaktivnosti.', rtrim(rtrim(number_format((float) $minutes, 2, '.', ''), '0'), '.')));

        return new RedirectResponse($target?->getId() ? $this->urls->generate('admin_user_show', ['id' => $target->getId()]) : $this->urls->generate('app_dashboard'));
    }

    private function deny(Request $request, string $message): Response
    {
        if (str_starts_with($request->getPathInfo(), '/ajax/') || $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'json')) {
            return new JsonResponse(['success' => false, 'data' => null, 'message' => $message], Response::HTTP_FORBIDDEN);
        }
        $this->flash($request, 'danger', $message);
        $referer = (string) $request->headers->get('Referer');
        $back = '' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost().'/') ? $referer : $this->urls->generate('app_dashboard');

        return new RedirectResponse($back, Response::HTTP_SEE_OTHER);
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if ($request->hasSession() && ($session = $request->getSession()) instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
