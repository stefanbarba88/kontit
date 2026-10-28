<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\JobSource;
use App\Repository\ClientJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Konkretan posao koji se radi za klijenta; iz ovoga nastaju zadaci (SPEC 8b, 8c).
 * Override vrednosti (raspored, estimatedMinutes, zaduženi) važe umesto onih iz posla kad nisu prazne.
 * Prazan override rasporeda (type = null) znači "kao u poslu" (SPEC 8g.3).
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
    #[Assert\NotNull(message: 'Izaberi posao.')]
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

    /** Drugačiji raspored za ovog klijenta; prazan = raspored posla */
    #[ORM\Embedded(class: Schedule::class)]
    #[Assert\Valid]
    private Schedule $scheduleOverride;

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
    #[Assert\NotNull(message: 'Unesi početak.')]
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

    public function __construct()
    {
        $this->scheduleOverride = new Schedule();
    }

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

    public function getScheduleOverride(): Schedule
    {
        // Doctrine ne pravi ugrađen objekat za stare redove bez kolona; prazan = kao u poslu
        return $this->scheduleOverride ??= new Schedule();
    }

    public function setScheduleOverride(?Schedule $schedule): static
    {
        $this->scheduleOverride = $schedule ?? new Schedule();

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

    /**
     * Raspored koji važi: override ako je zadat, inače raspored posla.
     */
    public function getSchedule(): Schedule
    {
        return $this->getScheduleOverride()->isEmpty() ? $this->jobTemplate->getSchedule() : $this->scheduleOverride;
    }

    public function hasScheduleOverride(): bool
    {
        return !$this->getScheduleOverride()->isEmpty();
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
