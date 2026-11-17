<?php

namespace App\Service;

use App\Entity\Schedule;
use App\Enum\NonWorkingDayShift;
use App\Enum\ScheduleType;

/**
 * Jedino mesto za logiku rasporeda posla (SPEC 8g.3): rokovi, ključ perioda i opis rečima.
 *
 * Nominalni rok (pre pomeranja) i ključ perioda:
 *  - DAILY: svaki N-ti dan od početka (anchor); "samo radni dani" preskače neradne. Ključ 2026-11-03. Dnevni rok se ne pomera.
 *  - WEEKLY: dan u nedelji, na svakih N nedelja od nedelje početka. Ključ 2026-W45.
 *  - MONTHLY: dan u mesecu (31 ili "poslednji" = poslednji dan meseca), na svakih N meseci od meseca početka. Ključ 2026-11.
 *  - QUARTERLY: dan u 1., 2. ili 3. mesecu posle isteka kvartala. Ključ je kvartal koji je istekao (rok 20.1.2027 = 2026-Q4).
 *  - YEARLY: mesec i dan. Ključ je godina roka (2026).
 * Rok koji padne na neradni dan pomera se na prvi sledeći (NEXT) ili prethodni (PREVIOUS) radni dan.
 */
class ScheduleCalculator
{
    public const MONTHS = ['januar', 'februar', 'mart', 'april', 'maj', 'jun', 'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar'];
    private const MONTHS_GENITIVE = ['januara', 'februara', 'marta', 'aprila', 'maja', 'juna', 'jula', 'avgusta', 'septembra', 'oktobra', 'novembra', 'decembra'];
    private const WEEKDAYS = [1 => 'ponedeljak', 'utorak', 'sreda', 'četvrtak', 'petak', 'subota', 'nedelja'];
    private const WEEKDAYS_INSTR = [1 => 'ponedeljkom', 'utorkom', 'sredom', 'četvrtkom', 'petkom', 'subotom', 'nedeljom'];
    private const WEEKDAYS_GEN = [1 => 'svakog ponedeljka', 'svakog utorka', 'svake srede', 'svakog četvrtka', 'svakog petka', 'svake subote', 'svake nedelje'];

    /** Koliko unazad od "from" se kreće, da se uhvate rokovi koje PREVIOUS pomera unapred u opseg (najduži kolektivni odmor) */
    private const LOOKAROUND_DAYS = 40;

    public function __construct(private readonly WorkingDayService $workingDays)
    {
    }

    /**
     * Narednih $count rokova od $from (uključujući), već pomerenih zbog neradnih dana.
     *
     * @return list<array{key: string, label: string, nominal: \DateTimeImmutable, due: \DateTimeImmutable, shifted: bool, reason: ?string}>
     */
    public function nextOccurrences(Schedule $schedule, \DateTimeInterface $from, int $count, ?\DateTimeInterface $anchor = null): array
    {
        $from = $this->day($from);
        $result = [];
        // nominalni rokovi idu redom, a pomeranje ih pomera najviše za LOOKAROUND_DAYS, pa je dovoljno skupiti malo više i sortirati
        foreach ($this->nominal($schedule, $from->modify('-'.self::LOOKAROUND_DAYS.' days'), $anchor) as $nominal) {
            $occ = $this->occurrence($schedule, $nominal);
            if ($occ['due'] >= $from) {
                $result[] = $occ;
            }
            if (\count($result) >= $count && $nominal > end($result)['due']->modify('+'.self::LOOKAROUND_DAYS.' days')) {
                break;
            }
        }

        return \array_slice($this->sorted($result), 0, $count);
    }

    /**
     * Svi rokovi (pomereni) u opsegu [$from, $to].
     *
     * @return list<array{key: string, label: string, nominal: \DateTimeImmutable, due: \DateTimeImmutable, shifted: bool, reason: ?string}>
     */
    public function occurrencesBetween(Schedule $schedule, \DateTimeInterface $from, \DateTimeInterface $to, ?\DateTimeInterface $anchor = null): array
    {
        $from = $this->day($from);
        $to = $this->day($to);
        $result = [];
        foreach ($this->nominal($schedule, $from->modify('-'.self::LOOKAROUND_DAYS.' days'), $anchor) as $nominal) {
            if ($nominal > $to->modify('+'.self::LOOKAROUND_DAYS.' days')) {
                break;
            }
            $occ = $this->occurrence($schedule, $nominal);
            if ($occ['due'] >= $from && $occ['due'] <= $to) {
                $result[] = $occ;
            }
        }

        return $this->sorted($result);
    }

    /**
     * Ključ perioda za nominalni rok.
     */
    public function periodKey(Schedule $schedule, \DateTimeInterface $date): string
    {
        $date = $this->day($date);

        return match ($schedule->getType()) {
            ScheduleType::DAILY => $date->format('Y-m-d'),
            ScheduleType::WEEKLY => $date->format('o-\WW'),
            ScheduleType::MONTHLY, ScheduleType::NTH_WORKDAY => $date->format('Y-m'),
            ScheduleType::QUARTERLY => $this->quarterKey($schedule, $date),
            ScheduleType::YEARLY => $date->format('Y'),
            default => 'MANUAL',
        };
    }

    /**
     * Period rečima, za naslov zadatka ("novembar 2026", "Q4 2026", "nedelja 45/2026", "03.11.2026.").
     */
    public function periodLabel(Schedule $schedule, \DateTimeInterface $date): string
    {
        $date = $this->day($date);

        return match ($schedule->getType()) {
            ScheduleType::DAILY => $date->format('d.m.Y.'),
            ScheduleType::WEEKLY => sprintf('nedelja %s/%s', $date->format('W'), $date->format('o')),
            ScheduleType::MONTHLY, ScheduleType::NTH_WORKDAY => self::MONTHS[(int) $date->format('n') - 1].' '.$date->format('Y'),
            ScheduleType::QUARTERLY => (static fn (array $p) => $p[1].' '.$p[0])(explode('-', $this->quarterKey($schedule, $date))),
            ScheduleType::YEARLY => $date->format('Y'),
            default => $date->format('d.m.Y.'),
        };
    }

    /**
     * Opis rečima: "Mesečno, 15. u mesecu (ako je neradni dan, prvi sledeći radni dan)".
     * $short daje kratak oblik za liste ("Mesečno, 15.").
     */
    public function describe(Schedule $schedule, bool $short = false): string
    {
        $n = max(1, $schedule->getInterval() ?? 1);
        $day = $this->dayText($schedule->getDayOfMonth());

        $text = match ($schedule->getType()) {
            null, ScheduleType::NONE => $short ? 'Po potrebi' : 'Po potrebi (zadatak se pravi ručno)',
            ScheduleType::DAILY => match (true) {
                1 === $n && $schedule->isWorkingDaysOnly() => $short ? 'Dnevno, radni dani' : 'Svakog radnog dana',
                1 === $n => 'Svaki dan',
                default => sprintf('Na svakih %d dana%s', $n, $schedule->isWorkingDaysOnly() ? ' (samo radni dani)' : ''),
            },
            ScheduleType::WEEKLY => 1 === $n
                ? ($short ? 'Nedeljno, '.self::WEEKDAYS[$schedule->getDayOfWeek() ?? 1] : ucfirst(self::WEEKDAYS_GEN[$schedule->getDayOfWeek() ?? 1]))
                : sprintf('Na %s, %s', $this->plural($n, 'svaku %d. nedelju', 'svake %d nedelje', 'svakih %d nedelja'), self::WEEKDAYS_INSTR[$schedule->getDayOfWeek() ?? 1]),
            ScheduleType::MONTHLY => 1 === $n
                ? ($short ? 'Mesečno, '.$day : 'Mesečno, '.$day.' u mesecu')
                : sprintf('Na %s, %s u mesecu', $this->plural($n, 'svaki %d. mesec', 'svaka %d meseca', 'svakih %d meseci'), $day),
            ScheduleType::NTH_WORKDAY => sprintf('%d. radni dan u mesecu', $schedule->getNthWorkingDay() ?? 1),
            ScheduleType::QUARTERLY => $short
                ? sprintf('Kvartalno, %s (%d. mesec)', $day, $schedule->getMonthOfQuarter() ?? 1)
                : sprintf('Kvartalno, %s u %d. mesecu posle kvartala', $day, $schedule->getMonthOfQuarter() ?? 1),
            ScheduleType::YEARLY => sprintf('Godišnje, %s', Schedule::LAST_DAY === $schedule->getDayOfMonth()
                ? 'poslednji dan '.self::MONTHS_GENITIVE[($schedule->getMonth() ?? 1) - 1]
                : ($schedule->getDayOfMonth() ?? 1).'. '.self::MONTHS[($schedule->getMonth() ?? 1) - 1]),
        };

        // N-ti radni dan je uvek radni dan, pa pravilo za neradni dan ne važi
        if (!$short && $schedule->isAutomatic() && !\in_array($schedule->getType(), [ScheduleType::DAILY, ScheduleType::NTH_WORKDAY], true)) {
            $text .= match ($schedule->getNonWorkingDayShift()) {
                NonWorkingDayShift::PREVIOUS => ' (ako je neradni dan, prethodni radni dan)',
                NonWorkingDayShift::EXACT => ' (tačno tog dana, i ako je neradni)',
                default => ' (ako je neradni dan, prvi sledeći radni dan)',
            };
        }

        return $text;
    }

    /**
     * Koliko puta mesečno se posao izvršava u proseku (za prihod i opterećenje u analitici).
     */
    public function perMonth(Schedule $schedule): float
    {
        $n = max(1, $schedule->getInterval() ?? 1);

        return match ($schedule->getType()) {
            ScheduleType::DAILY => ($schedule->isWorkingDaysOnly() ? 21.0 : 30.4) / $n,
            ScheduleType::WEEKLY => 52 / 12 / $n,
            ScheduleType::MONTHLY => 1 / $n,
            ScheduleType::NTH_WORKDAY => 1.0,
            ScheduleType::QUARTERLY => 1 / 3,
            ScheduleType::YEARLY => 1 / 12,
            default => 0.0,
        };
    }

    /**
     * @return array{key: string, label: string, nominal: \DateTimeImmutable, due: \DateTimeImmutable, shifted: bool, reason: ?string}
     */
    private function occurrence(Schedule $schedule, \DateTimeImmutable $nominal): array
    {
        $due = $nominal;
        $reason = null;
        $clamped = false;
        if (ScheduleType::NTH_WORKDAY === $schedule->getType()) {
            // nominalni datum je već radni dan; samo treba znati da li je mesec imao manje radnih dana od traženog
            $clamped = $this->workingDays->nthWorkingDayOfMonth((int) $nominal->format('Y'), (int) $nominal->format('n'), $schedule->getNthWorkingDay() ?? 1)['clamped'];
        } elseif (ScheduleType::DAILY !== $schedule->getType() && !$this->workingDays->isWorkingDay($nominal)) {
            $reason = $this->workingDays->reason($nominal);
            $due = match ($schedule->getNonWorkingDayShift()) {
                NonWorkingDayShift::EXACT => $nominal, // bez pomeranja; reason ostaje za upozorenje u previewu
                NonWorkingDayShift::PREVIOUS => $this->workingDays->previousWorkingDay($nominal),
                default => $this->workingDays->nextWorkingDay($nominal),
            };
        }

        return [
            'key' => $this->periodKey($schedule, $nominal),
            'label' => $this->periodLabel($schedule, $nominal),
            'nominal' => $nominal,
            'due' => $due,
            'shifted' => $due != $nominal,
            'reason' => $reason,
            'clamped' => $clamped,
        ];
    }

    /**
     * Nominalni rokovi redom, od približno $from (beskonačan niz; pozivalac prekida).
     *
     * @return \Generator<\DateTimeImmutable>
     */
    private function nominal(Schedule $schedule, \DateTimeImmutable $from, ?\DateTimeInterface $anchor): \Generator
    {
        $anchor = null !== $anchor ? $this->day($anchor) : null;
        $n = max(1, $schedule->getInterval() ?? 1);
        $start = null !== $anchor && $anchor > $from ? $anchor : $from;
        $limit = $start->modify('+30 years');

        switch ($schedule->getType()) {
            case ScheduleType::DAILY:
                $base = $anchor ?? $start;
                $offset = max(0, (int) $base->diff($start)->format('%r%a'));
                $d = $base->modify('+'.(intdiv($offset, $n) * $n).' days');
                for (; $d < $limit; $d = $d->modify('+'.$n.' days')) {
                    if (!$schedule->isWorkingDaysOnly() || $this->workingDays->isWorkingDay($d)) {
                        yield $d;
                    }
                }
                break;

            case ScheduleType::WEEKLY:
                $weekday = $schedule->getDayOfWeek() ?? 1;
                $baseWeek = ($anchor ?? $start)->modify('monday this week');
                $week = $start->modify('monday this week');
                $weeks = max(0, intdiv((int) $baseWeek->diff($week)->format('%r%a'), 7));
                $week = $baseWeek->modify('+'.(intdiv($weeks, $n) * $n).' weeks');
                for (; $week < $limit; $week = $week->modify('+'.$n.' weeks')) {
                    $d = $week->modify('+'.($weekday - 1).' days');
                    if (null === $anchor || $d >= $anchor) {
                        yield $d;
                    }
                }
                break;

            case ScheduleType::MONTHLY:
                $baseMonth = ($anchor ?? $start)->modify('first day of this month');
                $months = max(0, ((int) $start->format('Y') - (int) $baseMonth->format('Y')) * 12 + (int) $start->format('n') - (int) $baseMonth->format('n'));
                $months = intdiv($months, $n) * $n;
                for ($m = $baseMonth->modify('+'.$months.' months'); $m < $limit; $m = $m->modify('+'.$n.' months')) {
                    $d = $this->clampDay($m, $schedule->getDayOfMonth() ?? 15);
                    if (null === $anchor || $d >= $anchor) {
                        yield $d;
                    }
                }
                break;

            case ScheduleType::NTH_WORKDAY:
                // svaki mesec (interval se ne koristi); nominalni rok je već radni dan
                $nth = $schedule->getNthWorkingDay() ?? 1;
                for ($m = $start->modify('first day of this month'); $m < $limit; $m = $m->modify('+1 month')) {
                    $d = $this->workingDays->nthWorkingDayOfMonth((int) $m->format('Y'), (int) $m->format('n'), $nth)['date'];
                    if (null === $anchor || $d >= $anchor) {
                        yield $d;
                    }
                }
                break;

            case ScheduleType::QUARTERLY:
                $offset = ($schedule->getMonthOfQuarter() ?? 1) - 1;
                // prvi mesec posle kvartala: januar, april, jul, oktobar (+ offset)
                $y = (int) $start->format('Y') - 1;
                for (; $y < (int) $limit->format('Y'); ++$y) {
                    foreach ([1, 4, 7, 10] as $first) {
                        $d = $this->clampDay(new \DateTimeImmutable(sprintf('%d-%02d-01', $y, $first + $offset)), $schedule->getDayOfMonth() ?? 15);
                        if ($d >= $start->modify('-3 months') && (null === $anchor || $d >= $anchor)) {
                            yield $d;
                        }
                    }
                }
                break;

            case ScheduleType::YEARLY:
                for ($y = (int) $start->format('Y') - 1; $y < (int) $limit->format('Y'); ++$y) {
                    $d = $this->clampDay(new \DateTimeImmutable(sprintf('%d-%02d-01', $y, $schedule->getMonth() ?? 1)), $schedule->getDayOfMonth() ?? 15);
                    if (null === $anchor || $d >= $anchor) {
                        yield $d;
                    }
                }
                break;

            default:
                return;
        }
    }

    private function quarterKey(Schedule $schedule, \DateTimeImmutable $date): string
    {
        // rok je u (monthOfQuarter). mesecu posle kvartala, pa se vraćamo na poslednji mesec kvartala koji je istekao
        $ended = $date->modify('first day of this month')->modify('-'.($schedule->getMonthOfQuarter() ?? 1).' months');

        return sprintf('%s-Q%d', $ended->format('Y'), intdiv((int) $ended->format('n') - 1, 3) + 1);
    }

    private function clampDay(\DateTimeImmutable $month, int $day): \DateTimeImmutable
    {
        $last = (int) $month->format('t');
        $day = Schedule::LAST_DAY === $day ? $last : min(max(1, $day), $last);

        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day);
    }

    private function dayText(?int $day): string
    {
        return Schedule::LAST_DAY === $day ? 'poslednji dan' : ($day ?? 15).'.';
    }

    private function plural(int $n, string $one, string $few, string $many): string
    {
        // srpski: 2-4 "svaka 2 meseca", 5+ "svakih 5 meseci"
        return sprintf($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14) ? $few : ($n % 10 === 1 && $n % 100 !== 11 ? $one : $many), $n);
    }

    /**
     * @param list<array{due: \DateTimeImmutable, key: string}> $items
     *
     * @return list<array{key: string, label: string, nominal: \DateTimeImmutable, due: \DateTimeImmutable, shifted: bool, reason: ?string}>
     */
    private function sorted(array $items): array
    {
        usort($items, static fn (array $a, array $b) => [$a['due'], $a['key']] <=> [$b['due'], $b['key']]);
        // isti rok za isti period se ne ponavlja
        $seen = [];

        return array_values(array_filter($items, static function (array $i) use (&$seen) {
            if (isset($seen[$i['key']])) {
                return false;
            }

            return $seen[$i['key']] = true;
        }));
    }

    private function day(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
