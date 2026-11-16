<?php

namespace App\Service;

use App\Entity\ImpersonationLog;
use App\Entity\User;
use App\Enum\ImpersonationEnd;
use App\Enum\ImpersonationMode;
use App\Repository\ImpersonationLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Stanje impersonacije "Prikaži kao korisnik" (SPEC 8s): ko gleda, čiji nalog, u kom režimu, kad je poslednja aktivnost.
 * Izvor istine da je impersonacija aktivna je token (SwitchUserToken); režim i vreme su u sesiji, a evidencija u ImpersonationLog.
 * Jedina klasa koja zna za sesiju i evidenciju, pa kontroleri, Twig i subscriber ne računaju ništa sami.
 */
class ImpersonationState
{
    private const KEY_LOG = '_imp_log';
    private const KEY_MODE = '_imp_mode';
    private const KEY_LAST = '_imp_last';
    private const LOG_TOUCH_SECONDS = 60;

    public function __construct(
        private readonly TokenStorageInterface $tokens,
        private readonly RequestStack $requests,
        private readonly EntityManagerInterface $em,
        private readonly ImpersonationLogRepository $logs,
        private readonly ClockInterface $clock,
        #[Autowire('%impersonation.idle_minutes%')] private readonly int|float $idleMinutes,
    ) {
    }

    /**
     * Da li je u sesiji impersonacija u toku. Za razliku od isActive() ne treba token, pa radi i pre firewall-a
     * (ImpersonationSubscriber::hardenSwitch zabranjuje prebacivanje na drugog korisnika dok traje jedna).
     */
    public function isRunning(?SessionInterface $session = null): bool
    {
        return ($session ?? $this->session())?->has(self::KEY_LOG) ?? false;
    }

    public function isActive(): bool
    {
        return $this->tokens->getToken() instanceof SwitchUserToken;
    }

    /** Stvarni izvršilac (admin ili superadmin) dok traje impersonacija, inače null. */
    public function actor(): ?User
    {
        $token = $this->tokens->getToken();
        $user = $token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : null;

        return $user instanceof User ? $user : null;
    }

    /** Korisnik čiji se nalog gleda, dok traje impersonacija. */
    public function target(): ?User
    {
        $token = $this->tokens->getToken();
        $user = $token instanceof SwitchUserToken ? $token->getUser() : null;

        return $user instanceof User ? $user : null;
    }

    /** Stvarni izvršilac za istoriju izmena: pokretač impersonacije dok traje, inače prosleđeni (ulogovani) korisnik. */
    public function realUser(?User $current): ?User
    {
        return $this->actor() ?? $current;
    }

    /** Korisnik u čije ime se radi (samo dok impersonacija traje), za ActivityLog.onBehalfOf. */
    public function onBehalfOf(): ?User
    {
        return $this->target();
    }

    public function mode(): ImpersonationMode
    {
        return ImpersonationMode::tryFrom((string) $this->session()?->get(self::KEY_MODE)) ?? ImpersonationMode::VIEW;
    }

    public function isEditing(): bool
    {
        return $this->isActive() && ImpersonationMode::EDIT === $this->mode();
    }

    public function idleMinutes(): int|float
    {
        return $this->idleMinutes;
    }

    public function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }

    /** Početak: upisuje evidenciju i postavlja režim "samo pregled". */
    public function start(User $actor, User $target, ?string $ip, ?SessionInterface $session = null): ImpersonationLog
    {
        $session ??= $this->session();
        $now = $this->now();
        $log = new ImpersonationLog($actor, $target, $ip, $now);
        $this->em->persist($log);
        $this->em->flush();
        $session?->set(self::KEY_LOG, $log->getId());
        $session?->set(self::KEY_MODE, ImpersonationMode::VIEW->value);
        $session?->set(self::KEY_LAST, $now->getTimestamp());

        return $log;
    }

    /** Kraj: zatvara evidenciju i briše stanje iz sesije. Bezbedno je zvati i kad nema otvorenog zapisa. */
    public function end(ImpersonationEnd $reason, ?SessionInterface $session = null): void
    {
        $session ??= $this->session();
        $log = $this->currentLog($session);
        if (null !== $log && $log->isOpen()) {
            $log->end($reason, $this->now());
            $this->em->flush();
        }
        $session?->remove(self::KEY_LOG);
        $session?->remove(self::KEY_MODE);
        $session?->remove(self::KEY_LAST);
    }

    /** Prebacuje režim. Prvi ulazak u režim izmena se pamti u evidenciji. */
    public function setMode(ImpersonationMode $mode): void
    {
        $session = $this->session();
        $session?->set(self::KEY_MODE, $mode->value);
        if (ImpersonationMode::EDIT === $mode) {
            $this->currentLog($session)?->enterEditMode($this->now());
            $this->em->flush();
        }
    }

    /** Da li je prošlo više od praga od poslednje aktivnosti. */
    public function isExpired(): bool
    {
        $last = $this->session()?->get(self::KEY_LAST);
        if (!\is_int($last)) {
            return false;
        }

        return $this->now()->getTimestamp() - $last > (int) round($this->idleMinutes * 60);
    }

    /** Beleži aktivnost: sesija uvek, evidencija najviše jednom u minuti (bez upisa u bazu pri svakom zahtevu). */
    public function touch(): void
    {
        $session = $this->session();
        $now = $this->now();
        $session?->set(self::KEY_LAST, $now->getTimestamp());
        $log = $this->currentLog($session);
        if (null !== $log && $log->isOpen() && $now->getTimestamp() - $log->getLastActivityAt()->getTimestamp() >= self::LOG_TOUCH_SECONDS) {
            $log->touch($now);
            $this->em->flush();
        }
    }

    public function currentLog(?SessionInterface $session = null): ?ImpersonationLog
    {
        $id = ($session ?? $this->session())?->get(self::KEY_LOG);

        return \is_int($id) ? $this->logs->find($id) : null;
    }

    /** Zatvara zapise čija je poslednja aktivnost starija od praga (cron app:impersonation:close-stale). */
    public function closeStale(): int
    {
        $before = $this->now()->modify('-'.(int) round($this->idleMinutes * 60).' seconds');
        $count = 0;
        foreach ($this->logs->findStale($before) as $log) {
            $log->end(ImpersonationEnd::STALE, $this->now());
            ++$count;
        }
        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    private function session(): ?SessionInterface
    {
        try {
            return $this->requests->getSession();
        } catch (\Throwable) {
            return null;
        }
    }
}
