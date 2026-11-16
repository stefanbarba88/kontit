<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\ImpersonationEnd;
use App\Enum\ImpersonationMode;
use App\Repository\ImpersonationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Evidencija "Prikaži kao korisnik" (SPEC 8s): ko je, čiji nalog, od kada do kada, sa koje IP adrese i u kom režimu.
 * `mode` je NAJVIŠI režim koji je dostignut (ako je makar jednom bio režim izmena, ostaje EDIT), a `editStartedAt` je prvi ulazak u njega.
 * Zapis se nikad ne briše ni menja posle zatvaranja; korisnik vidi samo svoje zapise, superadmin sve.
 */
#[ORM\Entity(repositoryClass: ImpersonationLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'IDX_IMPERSONATION_TARGET', fields: ['target', 'startedAt'])]
#[ORM\Index(name: 'IDX_IMPERSONATION_OPEN', fields: ['endedAt', 'lastActivityAt'])]
class ImpersonationLog
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** stvarni izvršilac (admin ili superadmin) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $actor;

    /** korisnik čiji se nalog gleda */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $target;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastActivityAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $editStartedAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(length: 10, enumType: ImpersonationMode::class)]
    private ImpersonationMode $mode = ImpersonationMode::VIEW;

    #[ORM\Column(length: 10, enumType: ImpersonationEnd::class, nullable: true)]
    private ?ImpersonationEnd $endReason = null;

    public function __construct(User $actor, User $target, ?string $ip, ?\DateTimeImmutable $now = null)
    {
        $now ??= new \DateTimeImmutable();
        $this->actor = $actor;
        $this->target = $target;
        $this->ip = $ip;
        $this->startedAt = $now;
        $this->lastActivityAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActor(): User
    {
        return $this->actor;
    }

    public function getTarget(): User
    {
        return $this->target;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getLastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        if ($this->endedAt === null && $now > $this->lastActivityAt) {
            $this->lastActivityAt = $now;
        }
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function isOpen(): bool
    {
        return null === $this->endedAt;
    }

    /** Zatvara zapis; kraj je vreme poslednje aktivnosti kad je istekao, inače trenutak izlaza. */
    public function end(ImpersonationEnd $reason, \DateTimeImmutable $now): void
    {
        if (null !== $this->endedAt) {
            return;
        }
        $this->endReason = $reason;
        $this->endedAt = \in_array($reason, [ImpersonationEnd::TIMEOUT, ImpersonationEnd::STALE], true) ? $this->lastActivityAt : $now;
    }

    public function getEndReason(): ?ImpersonationEnd
    {
        return $this->endReason;
    }

    public function getMode(): ImpersonationMode
    {
        return $this->mode;
    }

    public function enterEditMode(\DateTimeImmutable $now): void
    {
        $this->mode = ImpersonationMode::EDIT;
        $this->editStartedAt ??= $now;
    }

    public function getEditStartedAt(): ?\DateTimeImmutable
    {
        return $this->editStartedAt;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    /** Trajanje u sekundama (otvoren zapis: do poslednje aktivnosti). */
    public function durationSeconds(): int
    {
        return max(0, (int) (($this->endedAt ?? $this->lastActivityAt)->getTimestamp() - $this->startedAt->getTimestamp()));
    }
}
