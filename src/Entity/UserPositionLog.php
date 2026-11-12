<?php

namespace App\Entity;

use App\Repository\UserPositionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Istorija napredovanja: promena pozicije korisnika. Uz veze čuva i imena pozicija u trenutku promene (snapshot),
 * pa istorija ostaje čitljiva i kad se pozicija preimenuje. Veze su SET NULL, a pozicija koja je u istoriji se ne briše.
 * Početno mapiranje starih tekstualnih pozicija (app:positions:migrate-legacy) NE pravi zapise ovde.
 */
#[ORM\Entity(repositoryClass: UserPositionLogRepository::class)]
#[ORM\Index(name: 'IDX_POSITION_LOG_USER', fields: ['user', 'changedAt'])]
class UserPositionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Position $fromPosition;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Position $toPosition;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fromPositionName;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $toPositionName;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $changedBy;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note;

    public function __construct(User $user, ?Position $from, ?Position $to, ?User $changedBy, ?string $note = null)
    {
        $this->user = $user;
        $this->fromPosition = $from;
        $this->toPosition = $to;
        $this->fromPositionName = $from?->getName();
        $this->toPositionName = $to?->getName();
        $this->changedBy = $changedBy;
        $note = null === $note ? '' : trim($note);
        $this->note = '' === $note ? null : mb_substr($note, 0, 255);
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getFromPosition(): ?Position
    {
        return $this->fromPosition;
    }

    public function getToPosition(): ?Position
    {
        return $this->toPosition;
    }

    /** Ime iz trenutka promene */
    public function getFromPositionName(): ?string
    {
        return $this->fromPositionName;
    }

    public function getToPositionName(): ?string
    {
        return $this->toPositionName;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}
