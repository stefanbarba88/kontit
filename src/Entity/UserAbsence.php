<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\AbsenceStatus;
use App\Repository\UserAbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Odsustvo radnika (SPEC 8j, "Absence"; ime klase je zadržano). Kolone se zovu startDate/endDate, jer su `from`/`to`
 * rezervisane reči u SQL-u. Zamena preuzima klijente samo dok je odsustvo odobreno (i automatski) i traje.
 * createdBy je podnosilac zahteva (requestedBy u SPEC-u).
 */
#[ORM\Entity(repositoryClass: UserAbsenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'IDX_ABSENCE_USER_DATES', fields: ['user', 'startDate', 'endDate'])]
#[ORM\Index(name: 'IDX_ABSENCE_STATUS_DEADLINE', fields: ['status', 'approvalDeadline'])]
class UserAbsence implements ApprovableInterface
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Izaberi vrstu odsustva.')]
    private ?AbsenceType $type = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Unesi početak odsustva.')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Unesi kraj odsustva.')]
    private ?\DateTimeImmutable $endDate = null;

    /** Pola dana (samo kad je odsustvo jedan dan) */
    #[ORM\Column(options: ['default' => false])]
    private bool $halfDay = false;

    /** Radni dani u periodu (bez vikenda, praznika i kolektivnog odmora); pola dana = 0,5 */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1, options: ['default' => '0.0'])]
    private string $workingDays = '0';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20, enumType: AbsenceStatus::class)]
    private AbsenceStatus $status = AbsenceStatus::ODOBRENO;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /** Ko je odobrio ili odbio (prazno dok čeka, ako je odmah odobreno pri upisu ili ako je odobreno automatski) */
    #[ORM\ManyToOne]
    private ?User $decidedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    /** Razlog odbijanja ili poništavanja automatskog odobrenja */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $decisionNote = null;

    /** Do kada odobravalac treba da odgovori; posle se zahtev automatski odobrava (SPEC 8m) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvalDeadline = null;

    /** Podsetnik odobravaocu 24 h pre isteka roka je poslat */
    #[ORM\Column(options: ['default' => false])]
    private bool $reminderSent = false;

    /** Podsetnik da nedostaje dokument (doznaka) je poslat (SPEC 8j.3: rok 3 dana) */
    #[ORM\Column(options: ['default' => false])]
    private bool $documentReminderSent = false;

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null !== $this->startDate && null !== $this->endDate && $this->endDate < $this->startDate) {
            $context->buildViolation('Kraj odsustva ne može biti pre početka.')->atPath('endDate')->addViolation();
        }
        if ($this->halfDay && null !== $this->startDate && $this->startDate != $this->endDate) {
            $context->buildViolation('Pola dana može samo za odsustvo od jednog dana.')->atPath('halfDay')->addViolation();
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

    public function getType(): ?AbsenceType
    {
        return $this->type;
    }

    public function setType(?AbsenceType $type): static
    {
        $this->type = $type;

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

    public function isHalfDay(): bool
    {
        return $this->halfDay;
    }

    public function setHalfDay(bool $halfDay): static
    {
        $this->halfDay = $halfDay;

        return $this;
    }

    public function getWorkingDays(): float
    {
        return (float) $this->workingDays;
    }

    public function setWorkingDays(float $days): static
    {
        $this->workingDays = number_format($days, 1, '.', '');

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

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    public function isPending(): bool
    {
        return AbsenceStatus::NA_ODOBRENJU === $this->status;
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

    public function getDecisionNote(): ?string
    {
        return $this->decisionNote;
    }

    public function decide(AbsenceStatus $status, ?User $by, ?string $note = null): static
    {
        $this->status = $status;
        $this->decidedBy = $by;
        $this->decidedAt = new \DateTimeImmutable();
        $this->decisionNote = null === $note || '' === trim($note) ? null : trim($note);

        return $this;
    }

    public function approve(User $by): void
    {
        $this->decide(AbsenceStatus::ODOBRENO, $by);
    }

    public function approveAutomatically(): void
    {
        $this->decide(AbsenceStatus::AUTOMATSKI_ODOBRENO, null);
    }

    public function getApprovalDeadline(): ?\DateTimeImmutable
    {
        return $this->approvalDeadline;
    }

    public function setApprovalDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->approvalDeadline = $deadline;

        return $this;
    }

    public function isDocumentReminderSent(): bool
    {
        return $this->documentReminderSent;
    }

    public function setDocumentReminderSent(bool $sent): static
    {
        $this->documentReminderSent = $sent;

        return $this;
    }

    public function isReminderSent(): bool
    {
        return $this->reminderSent;
    }

    public function setReminderSent(bool $sent): static
    {
        $this->reminderSent = $sent;

        return $this;
    }

    /**
     * Odobreno (i automatski) i datum $date upada u period (uključujući krajeve).
     */
    public function coversDate(\DateTimeImmutable $date): bool
    {
        return $this->status->isApproved()
            && null !== $this->startDate && null !== $this->endDate
            && $this->startDate <= $date && $this->endDate >= $date;
    }

    public function hasEnded(): bool
    {
        return null !== $this->endDate && $this->endDate < new \DateTimeImmutable('today');
    }

    /**
     * Kratak opis za istoriju i obaveštenja, npr. "10.11.2026. – 15.11.2026. (Godišnji odmor, 4 radna dana)".
     */
    public function summary(): string
    {
        $daysText = $this->halfDay ? 'pola dana' : self::daysText($this->getWorkingDays());
        $period = $this->startDate == $this->endDate
            ? $this->startDate?->format('d.m.Y.')
            : sprintf('%s – %s', $this->startDate?->format('d.m.Y.'), $this->endDate?->format('d.m.Y.'));

        return sprintf('%s (%s, %s)', $period, $this->type?->getName(), $daysText);
    }

    /**
     * "1 radni dan", "3 radna dana", "5 radnih dana", "1,5 radnih dana".
     */
    public static function daysText(float $days): string
    {
        $n = rtrim(rtrim(number_format($days, 1, ',', ''), '0'), ',');
        if (floor($days) != $days) {
            return $n.' radnih dana';
        }
        $i = (int) $days;

        return $n.' '.(1 === $i % 10 && 11 !== $i % 100 ? 'radni dan' : ($i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 12 || $i % 100 > 14) ? 'radna dana' : 'radnih dana'));
    }
}
