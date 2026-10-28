<?php

namespace App\Entity;

use App\Enum\NonWorkingDayShift;
use App\Enum\ScheduleType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Raspored izvršavanja posla (SPEC 8g.3). Na JobTemplate je raspored posla, a na ClientJob isti skup polja
 * služi kao override: prazan (type = null) znači "kao u poslu". Logiku rokova drži isključivo ScheduleCalculator.
 */
#[ORM\Embeddable]
class Schedule
{
    /** "Poslednji dan u mesecu" u dayOfMonth */
    public const LAST_DAY = -1;

    #[ORM\Column(length: 12, nullable: true, enumType: ScheduleType::class)]
    private ?ScheduleType $type = null;

    /** Na svakih N (dana, nedelja, meseci) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 365)]
    private ?int $interval = null;

    /** 1 = ponedeljak ... 7 = nedelja (WEEKLY) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 7)]
    private ?int $dayOfWeek = null;

    /** 1-31 ili LAST_DAY (MONTHLY, QUARTERLY, YEARLY) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -1, max: 31)]
    private ?int $dayOfMonth = null;

    /** Koji mesec posle isteka kvartala (1-3) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 3)]
    private ?int $monthOfQuarter = null;

    /** Mesec (YEARLY) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 12)]
    private ?int $month = null;

    /** DAILY: samo radni dani (neradni se preskaču) */
    #[ORM\Column(nullable: true)]
    private ?bool $workingDaysOnly = null;

    /** Koliko dana pre roka nastaje zadatak */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 120)]
    private ?int $leadDays = null;

    #[ORM\Column(length: 10, nullable: true, enumType: NonWorkingDayShift::class)]
    private ?NonWorkingDayShift $nonWorkingDayShift = null;

    public static function monthly(int $day = 15, int $leadDays = 10): self
    {
        return (new self())->setType(ScheduleType::MONTHLY)->setInterval(1)->setDayOfMonth($day)->setLeadDays($leadDays)->setNonWorkingDayShift(NonWorkingDayShift::NEXT);
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $require = fn (string $field, ?int $value, string $message) => null === $value ? $context->buildViolation($message)->atPath($field)->addViolation() : null;
        match ($this->type) {
            ScheduleType::WEEKLY => $require('dayOfWeek', $this->dayOfWeek, 'Izaberi dan u nedelji.'),
            ScheduleType::MONTHLY, ScheduleType::QUARTERLY => $require('dayOfMonth', $this->dayOfMonth, 'Unesi dan u mesecu.'),
            ScheduleType::YEARLY => [$require('month', $this->month, 'Izaberi mesec.'), $require('dayOfMonth', $this->dayOfMonth, 'Unesi dan u mesecu.')],
            default => null,
        };
        if (0 === $this->dayOfMonth) {
            $context->buildViolation('Dan u mesecu je 1-31 ili "poslednji dan".')->atPath('dayOfMonth')->addViolation();
        }
    }

    public function isEmpty(): bool
    {
        return null === $this->type;
    }

    public function isAutomatic(): bool
    {
        return null !== $this->type && ScheduleType::NONE !== $this->type;
    }

    /**
     * Podaci za AJAX pregled i za poređenje.
     *
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
            'interval' => $this->interval,
            'dayOfWeek' => $this->dayOfWeek,
            'dayOfMonth' => $this->dayOfMonth,
            'monthOfQuarter' => $this->monthOfQuarter,
            'month' => $this->month,
            'workingDaysOnly' => $this->workingDaysOnly,
            'leadDays' => $this->leadDays,
            'nonWorkingDayShift' => $this->nonWorkingDayShift?->value,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $int = static fn (string $key): ?int => isset($data[$key]) && '' !== $data[$key] && null !== $data[$key] ? (int) $data[$key] : null;

        return (new self())
            ->setType(ScheduleType::tryFrom((string) ($data['type'] ?? '')))
            ->setInterval($int('interval'))
            ->setDayOfWeek($int('dayOfWeek'))
            ->setDayOfMonth($int('dayOfMonth'))
            ->setMonthOfQuarter($int('monthOfQuarter'))
            ->setMonth($int('month'))
            ->setWorkingDaysOnly(isset($data['workingDaysOnly']) ? filter_var($data['workingDaysOnly'], \FILTER_VALIDATE_BOOL) : null)
            ->setLeadDays($int('leadDays'))
            ->setNonWorkingDayShift(NonWorkingDayShift::tryFrom((string) ($data['nonWorkingDayShift'] ?? '')));
    }

    public function getType(): ?ScheduleType
    {
        return $this->type;
    }

    public function setType(?ScheduleType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getInterval(): ?int
    {
        return $this->interval;
    }

    public function setInterval(?int $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(?int $dayOfMonth): static
    {
        $this->dayOfMonth = $dayOfMonth;

        return $this;
    }

    public function getMonthOfQuarter(): ?int
    {
        return $this->monthOfQuarter;
    }

    public function setMonthOfQuarter(?int $monthOfQuarter): static
    {
        $this->monthOfQuarter = $monthOfQuarter;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(?int $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function isWorkingDaysOnly(): ?bool
    {
        return $this->workingDaysOnly;
    }

    public function setWorkingDaysOnly(?bool $workingDaysOnly): static
    {
        $this->workingDaysOnly = $workingDaysOnly;

        return $this;
    }

    public function getLeadDays(): ?int
    {
        return $this->leadDays;
    }

    public function setLeadDays(?int $leadDays): static
    {
        $this->leadDays = $leadDays;

        return $this;
    }

    public function getNonWorkingDayShift(): ?NonWorkingDayShift
    {
        return $this->nonWorkingDayShift;
    }

    public function setNonWorkingDayShift(?NonWorkingDayShift $shift): static
    {
        $this->nonWorkingDayShift = $shift;

        return $this;
    }
}
