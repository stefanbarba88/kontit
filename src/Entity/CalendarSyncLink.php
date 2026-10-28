<?php

namespace App\Entity;

use App\Repository\CalendarSyncLinkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Koji događaj u Google kalendaru korisnika odgovara kom zadatku ili odsustvu (SPEC 8n.3).
 */
#[ORM\Entity(repositoryClass: CalendarSyncLinkRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SYNC_USER_ENTITY', columns: ['user_id', 'entity_type', 'entity_id'])]
#[ORM\Index(name: 'IDX_SYNC_ENTITY', fields: ['entityType', 'entityId'])]
class CalendarSyncLink
{
    public const TASK = 'Task';
    public const ABSENCE = 'Absence';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $entityType;

    #[ORM\Column]
    private int $entityId;

    #[ORM\Column(length: 255)]
    private string $googleEventId;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $etag = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $syncedAt;

    public function __construct(User $user, string $entityType, int $entityId, string $googleEventId, ?string $etag = null)
    {
        $this->user = $user;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->googleEventId = $googleEventId;
        $this->etag = $etag;
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getGoogleEventId(): string
    {
        return $this->googleEventId;
    }

    public function getEtag(): ?string
    {
        return $this->etag;
    }

    public function touch(string $googleEventId, ?string $etag): static
    {
        $this->googleEventId = $googleEventId;
        $this->etag = $etag;
        $this->syncedAt = new \DateTimeImmutable();

        return $this;
    }
}
