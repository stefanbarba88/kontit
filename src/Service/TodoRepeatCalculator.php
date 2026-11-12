<?php

namespace App\Service;

use App\Entity\TodoItem;
use App\Enum\TodoRepeat;

/**
 * Sledeći rok ponavljajuće stavke. Računa se po lokalnom (Belgrade) kalendaru, pa vreme dana ostaje isto i posle
 * prelaska na letnje/zimsko vreme. Ako stavka kasni, računa se od DANAŠNJEG dana (ne od starog roka), da ne nastane gomila zakasnelih.
 */
class TodoRepeatCalculator
{
    public function __construct(
        private readonly TodoClock $clock,
        private readonly WorkingDayService $workingDays,
    ) {
    }

    /**
     * @return array{dueAt: ?\DateTimeImmutable, remindAt: ?\DateTimeImmutable, repeatDay: ?int}|null null kad stavka nema nijedan datum (nema od čega da se računa)
     */
    public function next(TodoItem $item): ?array
    {
        $anchor = $item->getDueAt() ?? $item->getRemindAt();
        if (TodoRepeat::NONE === $item->getRepeat() || null === $anchor) {
            return null;
        }
        $anchorDate = $this->clock->localDate($anchor);
        $today = $this->clock->today();
        $base = max($anchorDate, $today);
        $baseDay = new \DateTimeImmutable($base);

        $repeatDay = null;
        $nextDate = match ($item->getRepeat()) {
            TodoRepeat::DAILY => $baseDay->modify('+1 day'),
            TodoRepeat::WORKDAYS => $this->workingDays->nextWorkingDay($baseDay->modify('+1 day')),
            TodoRepeat::WEEKLY => $baseDay->modify('+7 days'),
            TodoRepeat::MONTHLY => $this->nextMonthly($baseDay, $repeatDay = $item->getRepeatDay() ?? (int) (new \DateTimeImmutable($anchorDate))->format('j')),
        };

        $due = null;
        if (null !== $item->getDueAt()) {
            $due = $item->hasDueTime()
                ? $this->clock->fromLocal($nextDate->format('Y-m-d'), $this->clock->localTime($item->getDueAt()))
                : $this->clock->startOfDay($nextDate->format('Y-m-d'));
        }
        $remind = null;
        if (null !== $item->getRemindAt()) {
            // podsetnik zadržava isti razmak (u danima) prema roku i isto vreme dana
            $delta = (int) (new \DateTimeImmutable($anchorDate))->diff(new \DateTimeImmutable($this->clock->localDate($item->getRemindAt())))->format('%r%a');
            $remindDate = $nextDate->modify(sprintf('%+d days', $delta))->format('Y-m-d');
            $remind = $this->clock->fromLocal($remindDate, $this->clock->localTime($item->getRemindAt()));
        }

        return ['dueAt' => $due, 'remindAt' => $remind, 'repeatDay' => $repeatDay];
    }

    /** Prvi datum posle $base sa danom $day u mesecu (31. u kraćem mesecu postaje poslednji dan tog meseca) */
    private function nextMonthly(\DateTimeImmutable $base, int $day): \DateTimeImmutable
    {
        $candidate = $this->clampDay($base, $day);
        if ($candidate > $base) {
            return $candidate;
        }

        return $this->clampDay($base->modify('first day of next month'), $day);
    }

    private function clampDay(\DateTimeImmutable $inMonth, int $day): \DateTimeImmutable
    {
        $last = (int) $inMonth->format('t');

        return $inMonth->setDate((int) $inMonth->format('Y'), (int) $inMonth->format('n'), min($day, $last));
    }
}
