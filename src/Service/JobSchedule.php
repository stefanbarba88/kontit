<?php

namespace App\Service;

use App\Entity\ClientJob;
use App\Enum\Recurrence;
use App\Repository\HolidayRepository;

/**
 * Računa periode i rokove ponavljajućih poslova (SPEC 8c).
 *
 * Period i rok:
 *  - MONTHLY: ključ 2026-11, rok je dueDay tog meseca
 *  - QUARTERLY: ključ 2026-Q4, rok je dueDay meseca posle kraja kvartala (PDV za Q4 stiže u januaru)
 *  - YEARLY: ključ 2026, rok je dueDay.dueMonth te godine
 *  - WEEKLY: ključ 2026-W45, rok je dan u nedelji dueDay (1 = ponedeljak, podrazumevano 5)
 *  - ONE_TIME: ključ ONCE, rok je datum početka posla
 * Rok koji padne na vikend ili praznik pomera se na prvi sledeći radni dan.
 */
class JobSchedule
{
    private const MONTHS = ['januar', 'februar', 'mart', 'april', 'maj', 'jun', 'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar'];

    public function __construct(private readonly WorkingDayService $workingDays)
    {
    }

    /**
     * Pojave (period, rok) posla čiji rok pada u [$from, $to].
     *
     * @return list<array{key: string, label: string, due: \DateTimeImmutable}>
     */
    public function occurrences(ClientJob $job, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = [];
        foreach ($this->candidates($job, $from, $to) as $candidate) {
            $due = $this->nextWorkday($candidate['due']);
            if ($due >= $from && $due <= $to && $due >= $job->getStartDate() && (null === $job->getEndDate() || $due <= $job->getEndDate()->modify('+31 days'))) {
                $result[] = ['key' => $candidate['key'], 'label' => $candidate['label'], 'due' => $due];
            }
        }
        usort($result, static fn (array $a, array $b) => $a['due'] <=> $b['due']);

        return $result;
    }

    /**
     * Prvi radni dan na ili posle datuma (preskače subotu, nedelju i praznike).
     */
    public function nextWorkday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $this->workingDays->nextWorkingDay($date);
    }

    /**
     * @return list<array{key: string, label: string, due: \DateTimeImmutable}>
     */
    private function candidates(ClientJob $job, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $recurrence = $job->getRecurrence();
        $day = $job->getDueDay();
        $out = [];

        switch ($recurrence) {
            case Recurrence::ONE_TIME:
                $out[] = ['key' => 'ONCE', 'label' => $job->getStartDate()->format('d.m.Y.'), 'due' => $job->getStartDate()];
                break;

            case Recurrence::WEEKLY:
                $weekday = max(1, min(7, $day ?? 5));
                for ($cursor = $from->modify('monday this week')->modify('-7 days'); $cursor <= $to->modify('+7 days'); $cursor = $cursor->modify('+7 days')) {
                    $due = $cursor->modify('+'.($weekday - 1).' days');
                    $out[] = ['key' => $cursor->format('o-\WW'), 'label' => sprintf('nedelja %s/%s', $cursor->format('W'), $cursor->format('o')), 'due' => $due];
                }
                break;

            case Recurrence::MONTHLY:
                for ($m = $from->modify('first day of this month')->modify('-1 month'); $m <= $to->modify('+1 month'); $m = $m->modify('first day of next month')) {
                    $out[] = [
                        'key' => $m->format('Y-m'),
                        'label' => self::MONTHS[(int) $m->format('n') - 1].' '.$m->format('Y'),
                        'due' => $this->clampDay($m, $day ?? 15),
                    ];
                }
                break;

            case Recurrence::QUARTERLY:
                for ($y = (int) $from->format('Y') - 1; $y <= (int) $to->format('Y'); ++$y) {
                    for ($q = 1; $q <= 4; ++$q) {
                        $afterQuarter = new \DateTimeImmutable(sprintf('%d-%02d-01', $q < 4 ? $y : $y + 1, $q < 4 ? $q * 3 + 1 : 1));
                        $out[] = ['key' => sprintf('%d-Q%d', $y, $q), 'label' => sprintf('Q%d %d', $q, $y), 'due' => $this->clampDay($afterQuarter, $day ?? 15)];
                    }
                }
                break;

            case Recurrence::YEARLY:
                for ($y = (int) $from->format('Y') - 1; $y <= (int) $to->format('Y'); ++$y) {
                    $month = new \DateTimeImmutable(sprintf('%d-%02d-01', $y, $job->getJobTemplate()->getDueMonth() ?? 1));
                    $out[] = ['key' => (string) $y, 'label' => (string) $y, 'due' => $this->clampDay($month, $day ?? 15)];
                }
                break;
        }

        return $out;
    }

    private function clampDay(\DateTimeImmutable $monthStart, int $day): \DateTimeImmutable
    {
        $last = (int) $monthStart->format('t');

        return $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('n'), min($day, $last));
    }
}
