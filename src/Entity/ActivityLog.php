<?php

namespace App\Entity;

use App\Enum\ActivityAction;
use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Generička istorija izmena. Upisuje je isključivo ActivityLogListener.
 * Izmene stavki checkliste, komentari i učesnici zadatka beleže se pod entitetom "Task",
 * da bi timeline zadatka bio jedan upit.
 */
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Index(name: 'IDX_ACTIVITY_ENTITY', fields: ['entityType', 'entityId', 'createdAt'])]
#[ORM\Index(name: 'IDX_ACTIVITY_USER', fields: ['user', 'createdAt'])]
class ActivityLog
{
    public const TASK = 'Task';
    public const CLIENT = 'Client';
    public const USER = 'User';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $entityType;

    #[ORM\Column]
    private int $entityId;

    /** Naziv entiteta u trenutku izmene (naslov zadatka, naziv klijenta...), da se prikaže i u tuđem timeline-u */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityLabel;

    /** Prazno za izmene bez prijavljenog korisnika (komandna linija) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(length: 20, enumType: ActivityAction::class)]
    private ActivityAction $action;

    /** @var array<string, array{0: ?string, 1: ?string}> naziv polja => [staro, novo] */
    #[ORM\Column(type: Types::JSON)]
    private array $changes;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, array{0: ?string, 1: ?string}> $changes
     */
    public function __construct(string $entityType, int $entityId, ?string $entityLabel, ?User $user, ActivityAction $action, array $changes = [])
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->entityLabel = $entityLabel;
        $this->user = $user;
        $this->action = $action;
        $this->changes = $changes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getAction(): ActivityAction
    {
        return $this->action;
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
