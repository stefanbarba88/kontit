<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\TeamLeaderLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Istorija šefova tima. Trenutni šef ima endedAt = null.
 */
#[ORM\Entity(repositoryClass: TeamLeaderLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TeamLeaderLog
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\ManyToOne]
    private ?User $changedBy = null;

    public function __construct(Team $team, User $user, ?User $changedBy)
    {
        $this->team = $team;
        $this->user = $user;
        $this->changedBy = $changedBy;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }
}
