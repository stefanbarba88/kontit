<?php

namespace App\Service;

/**
 * Rok za odobrenje (SPEC 8m): min(podnošenje + X radnih sati, kraj dana pre početka odsustva).
 * "Radni sati" su sati koji padaju u radne dane (vikend, praznici i kolektivni odmor se preskaču), bez radnog vremena od-do.
 */
class ApprovalDeadlineCalculator
{
    public function __construct(private readonly WorkingDayService $workingDays)
    {
    }

    public function deadline(\DateTimeImmutable $submittedAt, int $workingHours, ?\DateTimeImmutable $startDate = null): \DateTimeImmutable
    {
        $left = $workingHours * 3600;
        $cursor = $submittedAt;
        for ($guard = 0; $left > 0 && $guard < 400; ++$guard) {
            $nextDay = $cursor->setTime(0, 0)->modify('+1 day');
            if ($this->workingDays->isWorkingDay($cursor)) {
                $available = $nextDay->getTimestamp() - $cursor->getTimestamp();
                if ($available >= $left) {
                    $cursor = $cursor->modify('+'.$left.' seconds');
                    $left = 0;
                    break;
                }
                $left -= $available;
            }
            $cursor = $nextDay;
        }

        if (null !== $startDate) {
            // najkasnije do kraja dana pre početka odsustva
            $latest = $startDate->setTime(0, 0)->modify('-1 second');
            if ($latest < $cursor) {
                $cursor = $latest;
            }
        }

        return $cursor;
    }

    /**
     * Hitno: rok je kraći od punog broja radnih sati (npr. odsustvo je sutra).
     */
    public function isUrgent(\DateTimeImmutable $submittedAt, \DateTimeImmutable $deadline, int $workingHours): bool
    {
        return $deadline < $this->deadline($submittedAt, $workingHours);
    }
}
