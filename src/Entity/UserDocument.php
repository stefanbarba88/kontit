<?php

namespace App\Entity;

use App\Repository\UserDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dokument korisnika (SPEC 8l): ugovor, rešenje, potvrda, doznaka... Fajl je van public/ (var/storage/user-documents/{userId}/)
 * pod nasumičnim imenom i servira se samo kroz kontroler uz Voter USER_DOCUMENT_VIEW. Brisanje je soft (deletedAt),
 * a fajl se fizički briše tek kad ga admin trajno obriše iz korpe.
 */
#[ORM\Entity(repositoryClass: UserDocumentRepository::class)]
#[ORM\Index(name: 'IDX_DOC_USER_DELETED', fields: ['user', 'deletedAt'])]
#[ORM\Index(name: 'IDX_DOC_VALID_UNTIL', fields: ['validUntil'])]
class UserDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Izaberi kategoriju.')]
    private ?DocumentCategory $category = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Unesi naziv dokumenta.')]
    #[Assert\Length(max: 150)]
    private ?string $title = null;

    /** Ime fajla na disku (nasumično, bez veze sa originalnim imenom) */
    #[ORM\Column(length: 80)]
    private string $storedName;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $uploadedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Odsustvo uz koje ide dokument (npr. doznaka za bolovanje) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?UserAbsence $absence = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** Najmanji broj dana pre isteka za koji je poslato obaveštenje (30, pa 7) */
    #[ORM\Column(nullable: true)]
    private ?int $expiryNotifiedDays = null;

    public function __construct(User $user, User $uploadedBy, string $storedName, string $originalName, string $mimeType, int $size)
    {
        $this->user = $user;
        $this->uploadedBy = $uploadedBy;
        $this->storedName = $storedName;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCategory(): ?DocumentCategory
    {
        return $this->category;
    }

    public function setCategory(?DocumentCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function isPdf(): bool
    {
        return 'application/pdf' === $this->mimeType;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;
        $this->expiryNotifiedDays = null;

        return $this;
    }

    public function isExpired(): bool
    {
        return null !== $this->validUntil && $this->validUntil < new \DateTimeImmutable('today');
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = null === $note || '' === trim($note) ? null : trim($note);

        return $this;
    }

    public function getUploadedBy(): User
    {
        return $this->uploadedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAbsence(): ?UserAbsence
    {
        return $this->absence;
    }

    public function setAbsence(?UserAbsence $absence): static
    {
        $this->absence = $absence;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    public function restore(): static
    {
        $this->deletedAt = null;

        return $this;
    }

    public function getExpiryNotifiedDays(): ?int
    {
        return $this->expiryNotifiedDays;
    }

    public function setExpiryNotifiedDays(?int $days): static
    {
        $this->expiryNotifiedDays = $days;

        return $this;
    }
}
