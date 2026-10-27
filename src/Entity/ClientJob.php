<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\JobSource;
use App\Enum\Recurrence;
use App\Repository\ClientJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Konkretan posao koji se radi za klijenta; iz ovoga nastaju zadaci (SPEC 8b, 8c).
 * Vrednosti "override" (recurrence, dueDay, estimatedMinutes, assignee) važe umesto onih iz šablona kad nisu prazne.
 */
#[ORM\Entity(repositoryClass: ClientJobRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ClientJob
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
    #[Assert\NotNull(message: 'Izaberite posao.')]
    private ?JobTemplate $jobTemplate = null;

    #[ORM\Column(length: 20, enumType: JobSource::class)]
    private JobSource $source = JobSource::AD_HOC;

    #[ORM\ManyToOne]
    private ?ClientPackage $clientPackage = null;

    /** Posao iz paketa koji je za ovog klijenta isključen: zadaci se ne generišu */
    #[ORM\Column]
    private bool $isExcluded = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excludedReason = null;

    #[ORM\ManyToOne]
    private ?User $excludedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $excludedAt = null;

    #[ORM\Column(length: 20, nullable: true, enumType: Recurrence::class)]
    private ?Recurrence $recurrenceOverride = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 31)]
    private ?int $dueDayOverride = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $estimatedMinutesOverride = null;

    #[ORM\ManyToOne]
    private ?User $assigneeOverride = null;

    /** Cena ad hoc posla (za PACKAGE je prazna, jer je u ceni paketa) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $price = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Unesite početak.')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private bool $isActive = true;

    /** Ad hoc posao koji je predložio radnik; generiše se tek posle odobrenja admina */
    #[ORM\ManyToOne]
    private ?User $proposedBy = null;

    #[ORM\Column]
    private bool $isApproved = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getJobTemplate(): ?JobTemplate
    {
        return $this->jobTemplate;
    }

    public function setJobTemplate(?JobTemplate $jobTemplate): static
    {
        $this->jobTemplate = $jobTemplate;

        return $this;
    }

    public function getSource(): JobSource
    {
        return $this->source;
    }

    public function setSource(JobSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getClientPackage(): ?ClientPackage
    {
        return $this->clientPackage;
    }

    public function setClientPackage(?ClientPackage $clientPackage): static
    {
        $this->clientPackage = $clientPackage;

        return $this;
    }

    public function isExcluded(): bool
    {
        return $this->isExcluded;
    }

    public function exclude(string $reason, User $by): static
    {
        $this->isExcluded = true;
        $this->excludedReason = $reason;
        $this->excludedBy = $by;
        $this->excludedAt = new \DateTimeImmutable();

        return $this;
    }

    public function include(): static
    {
        $this->isExcluded = false;
        $this->excludedReason = null;
        $this->excludedBy = null;
        $this->excludedAt = null;

        return $this;
    }

    public function getExcludedReason(): ?string
    {
        return $this->excludedReason;
    }

    public function getExcludedBy(): ?User
    {
        return $this->excludedBy;
    }

    public function getExcludedAt(): ?\DateTimeImmutable
    {
        return $this->excludedAt;
    }

    public function getRecurrenceOverride(): ?Recurrence
    {
        return $this->recurrenceOverride;
    }

    public function setRecurrenceOverride(?Recurrence $recurrence): static
    {
        $this->recurrenceOverride = $recurrence;

        return $this;
    }

    public function getDueDayOverride(): ?int
    {
        return $this->dueDayOverride;
    }

    public function setDueDayOverride(?int $day): static
    {
        $this->dueDayOverride = $day;

        return $this;
    }

    public function getEstimatedMinutesOverride(): ?int
    {
        return $this->estimatedMinutesOverride;
    }

    public function setEstimatedMinutesOverride(?int $minutes): static
    {
        $this->estimatedMinutesOverride = $minutes;

        return $this;
    }

    public function getAssigneeOverride(): ?User
    {
        return $this->assigneeOverride;
    }

    public function setAssigneeOverride(?User $user): static
    {
        $this->assigneeOverride = $user;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = '' === $price ? null : $price;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $date): static
    {
        $this->startDate = $date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $date): static
    {
        $this->endDate = $date;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $active): static
    {
        $this->isActive = $active;

        return $this;
    }

    public function getProposedBy(): ?User
    {
        return $this->proposedBy;
    }

    public function setProposedBy(?User $user): static
    {
        $this->proposedBy = $user;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setApproved(bool $approved): static
    {
        $this->isApproved = $approved;

        return $this;
    }

    // --- efektivne vrednosti (override ili šablon) ---

    public function getRecurrence(): Recurrence
    {
        return $this->recurrenceOverride ?? $this->jobTemplate->getRecurrence();
    }

    public function getDueDay(): ?int
    {
        return $this->dueDayOverride ?? $this->jobTemplate->getDueDay();
    }

    public function getEstimatedMinutes(): int
    {
        return $this->estimatedMinutesOverride ?? $this->jobTemplate->getEstimatedMinutes();
    }

    /**
     * Zadatak se generiše samo za aktivan, neisključen i odobren posao čiji period traje.
     */
    public function isGeneratable(\DateTimeImmutable $on): bool
    {
        return $this->isActive && !$this->isExcluded && $this->isApproved
            && $this->jobTemplate->isActive()
            && null !== $this->startDate && $this->startDate <= $on
            && (null === $this->endDate || $this->endDate >= $on);
    }
}
