<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\AbsenceReason;
use App\Enum\AbsenceStatus;
use App\Repository\UserAbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Odsustvo radnika. Kolone se zovu startDate/endDate, jer su `from`/`to` rezervisane reči u SQL-u.
 * Backup preuzima klijente samo dok je odsustvo ODOBRENO i traje (vidi AbsenceService).
 */
#[ORM\Entity(repositoryClass: UserAbsenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'IDX_ABSENCE_USER_DATES', fields: ['user', 'startDate', 'endDate'])]
class UserAbsence
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Izaberi radnika.')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Unesi početak odsustva.')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Unesi kraj odsustva.')]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 20, enumType: AbsenceReason::class)]
    #[Assert\NotNull(message: 'Izaberi razlog.')]
    private ?AbsenceReason $reason = AbsenceReason::GODISNJI;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20, enumType: AbsenceStatus::class)]
    private AbsenceStatus $status = AbsenceStatus::ODOBRENO;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /** Ko je odobrio ili odbio (prazno dok čeka ili ako je odmah odobreno pri upisu) */
    #[ORM\ManyToOne]
    private ?User $decidedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null !== $this->startDate && null !== $this->endDate && $this->endDate < $this->startDate) {
            $context->buildViolation('Kraj odsustva ne može biti pre početka.')->atPath('endDate')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getReason(): ?AbsenceReason
    {
        return $this->reason;
    }

    public function setReason(?AbsenceReason $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = '' === $note ? null : $note;

        return $this;
    }

    public function getStatus(): AbsenceStatus
    {
        return $this->status;
    }

    public function setStatus(AbsenceStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function decide(AbsenceStatus $status, User $by): static
    {
        $this->status = $status;
        $this->decidedBy = $by;
        $this->decidedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Odobreno i datum $date upada u period (uključujući krajeve).
     */
    public function coversDate(\DateTimeImmutable $date): bool
    {
        return AbsenceStatus::ODOBRENO === $this->status
            && null !== $this->startDate && null !== $this->endDate
            && $this->startDate <= $date && $this->endDate >= $date;
    }

    public function hasEnded(): bool
    {
        return null !== $this->endDate && $this->endDate < new \DateTimeImmutable('today');
    }

    /**
     * Kratak opis za istoriju i obaveštenja, npr. "10.11.2026. – 15.11.2026. (Godišnji odmor)".
     */
    public function summary(): string
    {
        return sprintf('%s – %s (%s)', $this->startDate?->format('d.m.Y.'), $this->endDate?->format('d.m.Y.'), $this->reason?->label());
    }
}
