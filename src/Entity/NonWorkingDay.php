<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\NonWorkingDayType;
use App\Repository\NonWorkingDayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Praznik ili kolektivni odmor (SPEC 8k). Praznik koji se ponavlja koristi samo dan i mesec iz dateFrom/dateTo.
 * Pitanje "da li je dan radni" rešava isključivo WorkingDayService.
 */
#[ORM\Entity(repositoryClass: NonWorkingDayRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'IDX_NWD_DATES', fields: ['dateFrom', 'dateTo'])]
class NonWorkingDay
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: NonWorkingDayType::class)]
    private NonWorkingDayType $type = NonWorkingDayType::HOLIDAY_ONCE;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Unesi naziv.')]
    #[Assert\Length(max: 150)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Unesi datum.')]
    private ?\DateTimeImmutable $dateFrom = null;

    /** Kraj opsega (uključujući); jedan dan = isto što i dateFrom */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateTo = null;

    /** Ponavlja se svake godine (isti dan i mesec); true samo za HOLIDAY_RECURRING */
    #[ORM\Column]
    private bool $recurring = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function __construct(?NonWorkingDayType $type = null, ?string $name = null, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null)
    {
        if (null !== $type) {
            $this->setType($type);
        }
        $this->name = $name;
        $this->dateFrom = $from;
        $this->dateTo = $to ?? $from;
    }

    #[Assert\Callback]
    public function validateRange(ExecutionContextInterface $context): void
    {
        if (null !== $this->dateFrom && null !== $this->dateTo && $this->dateTo < $this->dateFrom) {
            $context->buildViolation('Kraj ne može biti pre početka.')->atPath('dateTo')->addViolation();
        }
        if ($this->recurring && null !== $this->dateFrom && null !== $this->dateTo && $this->dateFrom->format('Y') !== $this->dateTo->format('Y')) {
            $context->buildViolation('Praznik koji se ponavlja mora biti unutar iste godine.')->atPath('dateTo')->addViolation();
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function normalize(): void
    {
        $this->dateTo ??= $this->dateFrom;
        $this->recurring = NonWorkingDayType::HOLIDAY_RECURRING === $this->type;
    }

    /**
     * Datumi (Y-m-d) koje ovaj zapis pokriva u datoj godini.
     *
     * @return list<string>
     */
    public function datesInYear(int $year): array
    {
        if (null === $this->dateFrom) {
            return [];
        }
        $to = $this->dateTo ?? $this->dateFrom;
        if ($this->recurring) {
            $from = $this->dateFrom->setDate($year, (int) $this->dateFrom->format('n'), (int) $this->dateFrom->format('j'));
            $to = $to->setDate($year, (int) $to->format('n'), (int) $to->format('j'));
            // 29. februar u neprestupnoj godini ne postoji (setDate bi ga pretvorio u 1. mart)
            if ($from->format('m-d') !== $this->dateFrom->format('m-d')) {
                $from = $from->modify('+1 day');
            }
            if ($to->format('m-d') !== ($this->dateTo ?? $this->dateFrom)->format('m-d')) {
                $to = $to->modify('-1 day');
            }
        } else {
            $from = $this->dateFrom;
        }

        $dates = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            if ((int) $d->format('Y') === $year) {
                $dates[] = $d->format('Y-m-d');
            }
        }

        return $dates;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): NonWorkingDayType
    {
        return $this->type;
    }

    public function setType(NonWorkingDayType $type): static
    {
        $this->type = $type;
        $this->recurring = NonWorkingDayType::HOLIDAY_RECURRING === $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDateFrom(): ?\DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function setDateFrom(?\DateTimeImmutable $dateFrom): static
    {
        $this->dateFrom = $dateFrom;

        return $this;
    }

    public function getDateTo(): ?\DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function setDateTo(?\DateTimeImmutable $dateTo): static
    {
        $this->dateTo = $dateTo;

        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->recurring;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function isRange(): bool
    {
        return null !== $this->dateTo && $this->dateTo != $this->dateFrom;
    }
}
