<?php

namespace App\Service;

use App\Entity\ClientJob;
use App\Entity\Schedule;
use App\Entity\Task;
use App\Enum\ScheduleType;

/**
 * Jedino mesto koje odlučuje koji raspored važi (SPEC 8g.4): Task (ručno postavljen rok) > ClientJob (prilagođeno za klijenta) > JobTemplate (podrazumevano).
 *
 * Raspored sam po sebi postoji samo na šablonu i na ClientJob-u (override je celovit, prazan = nasleđuje šablon; `isEmpty()` je izvor istine).
 * Zadatak nema raspored nego ručno postavljen rok (`dueDateManuallySet`), koji pobeđuje sve što bi raspored izračunao.
 * Računanje rokova ostaje u ScheduleCalculator-u, koji od resolvera samo dobija raspored.
 */
class ScheduleResolver
{
    /** Koliko dana oko roka zadatka se traži period zadatka kad se računa "podrazumevani" rok */
    private const TASK_WINDOW_DAYS = 100;

    public function __construct(private readonly ScheduleCalculator $calculator)
    {
    }

    /**
     * Raspored koji važi za posao klijenta: override ako je zadat, inače raspored posla.
     */
    public function forClientJob(ClientJob $job): Schedule
    {
        return $this->isCustomized($job) ? $job->getScheduleOverride() : $job->getJobTemplate()->getSchedule();
    }

    public function isCustomized(ClientJob $job): bool
    {
        return !$job->getScheduleOverride()->isEmpty();
    }

    /**
     * Podrazumevani raspored (iz posla), koji važi kad klijent nije prilagođen.
     */
    public function defaultFor(ClientJob $job): Schedule
    {
        return $job->getJobTemplate()->getSchedule();
    }

    /**
     * Raspored po kome je zadatak nastao (prazno za zadatak koji nije vezan za posao klijenta).
     */
    public function forTask(Task $task): ?Schedule
    {
        $job = $task->getClientJob();

        return null === $job ? null : $this->forClientJob($job);
    }

    /**
     * Rokovi posla klijenta u opsegu: raspored koji važi, od početka do kraja posla.
     *
     * @return list<array{key: string, label: string, nominal: \DateTimeImmutable, due: \DateTimeImmutable, shifted: bool, reason: ?string}>
     */
    public function occurrences(ClientJob $job, \DateTimeInterface $from, \DateTimeInterface $to, ?Schedule $schedule = null): array
    {
        $end = $job->getEndDate();

        return array_values(array_filter(
            $this->calculator->occurrencesBetween($schedule ?? $this->forClientJob($job), $from, $to, $job->getStartDate()),
            static fn (array $o) => null === $end || $o['nominal'] <= $end,
        ));
    }

    /**
     * Rok koji bi period zadatka imao po rasporedu koji važi (ili po zadatom $schedule), bez obzira na ručnu izmenu.
     * Prazno za zadatak koji nije generisan iz posla ili ako period ne postoji u rasporedu.
     */
    public function scheduledDue(Task $task, ?Schedule $schedule = null): ?\DateTimeImmutable
    {
        $job = $task->getClientJob();
        $key = $task->getPeriodKey();
        $anchor = $task->getDueDate();
        if (null === $job || null === $key || null === $anchor) {
            return null;
        }

        $schedule ??= $this->forClientJob($job);
        if (!$schedule->isAutomatic()) {
            return null;
        }
        // godišnji rok se ručno može pomeriti dalje nego mesečni
        $days = ScheduleType::YEARLY === $schedule->getType() ? 400 : self::TASK_WINDOW_DAYS;
        foreach ($this->occurrences($job, $anchor->modify('-'.$days.' days'), $anchor->modify('+'.$days.' days'), $schedule) as $occ) {
            if ($occ['key'] === $key) {
                return $occ['due'];
            }
        }

        return null;
    }

    /**
     * Rok zadatka koji važi: ručno postavljen rok pobeđuje raspored.
     */
    public function effectiveDue(Task $task): ?\DateTimeImmutable
    {
        return $task->isDueDateManuallySet() ? $task->getDueDate() : ($this->scheduledDue($task) ?? $task->getDueDate());
    }
}
