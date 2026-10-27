<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\AssignmentType;
use App\Repository\ClientAssignmentLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Istorija dodela klijenta. Trenutna dodela za dati tip ima endedAt = null.
 */
#[ORM\Entity(repositoryClass: ClientAssignmentLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ClientAssignmentLog
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 10, enumType: AssignmentType::class)]
    private ?AssignmentType $type = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\ManyToOne]
    private ?User $changedBy = null;

    public function __construct(Client $client, User $user, AssignmentType $type, ?User $changedBy)
    {
        $this->client = $client;
        $this->user = $user;
        $this->type = $type;
        $this->changedBy = $changedBy;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getType(): ?AssignmentType
    {
        return $this->type;
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
