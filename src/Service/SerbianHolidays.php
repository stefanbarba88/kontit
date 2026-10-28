<?php

namespace App\Service;

use App\Enum\NonWorkingDayType;

/**
 * Zakonski neradni praznici Srbije za godinu (SPEC 8k, "Učitaj praznike Srbije za godinu"). Admin pregleda predlog pre čuvanja.
 * Uskršnji praznici (Veliki petak do drugog dana Vaskrsa) računaju se po pravoslavnom Uskrsu (julijanski kalendar).
 * Praznik koji padne u nedelju: ne radi se prvog narednog radnog dana (predlog, admin potvrđuje).
 */
final class SerbianHolidays
{
    /** Praznici sa istim datumom svake godine: [naziv, mesec, prvi dan, poslednji dan] */
    private const FIXED = [
        ['Nova godina', 1, 1, 2],
        ['Božić', 1, 7, 7],
        ['Dan državnosti', 2, 15, 16],
        ['Praznik rada', 5, 1, 2],
        ['Dan primirja u Prvom svetskom ratu', 11, 11, 11],
    ];

    /**
     * Pravoslavni Uskrs (Vaskrs) po Meeus-ovom julijanskom algoritmu, preveden u gregorijanski kalendar.
     */
    public static function orthodoxEaster(int $year): \DateTimeImmutable
    {
        $a = $year % 4;
        $b = $year % 7;
        $c = $year % 19;
        $d = (19 * $c + 15) % 30;
        $e = (2 * $a + 4 * $b - $d + 34) % 7;
        $month = intdiv($d + $e + 114, 31);
        $day = (($d + $e + 114) % 31) + 1;
        // razlika julijanskog i gregorijanskog kalendara: 13 dana za 1900-2099, i jedan dan više na svakih 100 godina koje nisu deljive sa 400
        $shift = intdiv($year, 100) - intdiv($year, 400) - 2;

        return (new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $day)))->modify(sprintf('+%d days', $shift));
    }

    /**
     * Predlog za godinu.
     *
     * @return list<array{type: NonWorkingDayType, name: string, from: \DateTimeImmutable, to: \DateTimeImmutable, sundayShift: bool}>
     */
    public static function forYear(int $year): array
    {
        $items = [];
        $taken = [];
        foreach (self::FIXED as [$name, $month, $first, $last]) {
            $from = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $first));
            $to = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $last));
            $items[] = ['type' => NonWorkingDayType::HOLIDAY_RECURRING, 'name' => $name, 'from' => $from, 'to' => $to, 'sundayShift' => false];
            for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
                $taken[$d->format('Y-m-d')] = true;
            }
        }

        $easter = self::orthodoxEaster($year);
        $items[] = ['type' => NonWorkingDayType::HOLIDAY_ONCE, 'name' => 'Uskršnji praznici (Veliki petak do Vaskršnjeg ponedeljka)', 'from' => $easter->modify('-2 days'), 'to' => $easter->modify('+1 day'), 'sundayShift' => false];
        for ($d = $easter->modify('-2 days'); $d <= $easter->modify('+1 day'); $d = $d->modify('+1 day')) {
            $taken[$d->format('Y-m-d')] = true;
        }

        // praznik u nedelju (osim Uskrsa, koji je uvek u nedelju): ne radi se prvi naredni radni dan
        foreach (self::FIXED as [$name, $month, $first, $last]) {
            for ($day = $first; $day <= $last; ++$day) {
                $date = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $day));
                if (7 !== (int) $date->format('N')) {
                    continue;
                }
                $next = $date->modify('+1 day');
                while ((int) $next->format('N') >= 6 || isset($taken[$next->format('Y-m-d')])) {
                    $next = $next->modify('+1 day');
                }
                $taken[$next->format('Y-m-d')] = true;
                $items[] = ['type' => NonWorkingDayType::HOLIDAY_ONCE, 'name' => $name.' (pada u nedelju, ne radi se '.$next->format('d.m.').')', 'from' => $next, 'to' => $next, 'sundayShift' => true];
            }
        }

        usort($items, static fn (array $x, array $y) => $x['from'] <=> $y['from']);

        return $items;
    }
}
