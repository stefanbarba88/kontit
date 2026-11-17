<?php

namespace App\Service;

use App\Enum\NonWorkingDayType;
use App\Repository\NonWorkingDayRepository;
use App\Repository\WeeklyNonWorkingRuleRepository;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Jedino mesto koje odgovara na pitanje "da li je ovo radni dan" (SPEC 8k): pravilo za dane u nedelji,
 * praznici (ponavljaju se ili samo te godine) i kolektivni odmor. Rezultat se kešira po godini,
 * a invalidate() se poziva posle svake izmene neradnih dana.
 */
class WorkingDayService
{
    private const DAY_NAMES = [1 => 'Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak', 'Subota', 'Nedelja'];

    /** @var array<int, array<string, array{name: string, type: string}>> */
    private array $years = [];

    /** @var list<int>|null */
    private ?array $weekly = null;

    public function __construct(
        private readonly NonWorkingDayRepository $days,
        private readonly WeeklyNonWorkingRuleRepository $rules,
        private readonly CacheInterface $cache,
    ) {
    }

    public function isWorkingDay(\DateTimeInterface $date): bool
    {
        return null === $this->reason($date);
    }

    /**
     * Zašto dan nije radni ("Nova godina", "Kolektivni odmor", "Subota"), ili null ako je radni.
     */
    public function reason(\DateTimeInterface $date): ?string
    {
        $special = $this->year((int) $date->format('Y'))[$date->format('Y-m-d')] ?? null;
        if (null !== $special) {
            return $special['name'];
        }
        $weekday = (int) $date->format('N');

        return \in_array($weekday, $this->weeklyDays(), true) ? self::DAY_NAMES[$weekday] : null;
    }

    /**
     * Prvi radni dan na dati dan ili posle njega (ako je dan radni, vraća se on).
     */
    public function nextWorkingDay(\DateTimeInterface $date): \DateTimeImmutable
    {
        return $this->walk($date, '+1 day');
    }

    /**
     * Poslednji radni dan na dati dan ili pre njega (ako je dan radni, vraća se on).
     */
    public function previousWorkingDay(\DateTimeInterface $date): \DateTimeImmutable
    {
        return $this->walk($date, '-1 day');
    }

    /**
     * Broj radnih dana u opsegu, uključujući oba kraja.
     */
    public function countWorkingDays(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $count = 0;
        for ($d = $this->day($from); $d <= $this->day($to); $d = $d->modify('+1 day')) {
            if ($this->isWorkingDay($d)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * N-ti radni dan u mesecu (vikend, praznici i kolektivni odmori se ne računaju).
     * Ako mesec ima manje od N radnih dana, vraća poslednji radni dan meseca sa clamped = true.
     *
     * @return array{date: \DateTimeImmutable, clamped: bool}
     */
    public function nthWorkingDayOfMonth(int $year, int $month, int $n): array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        $found = 0;
        $lastWorking = null;
        for ($d = $first; $d <= $last; $d = $d->modify('+1 day')) {
            if ($this->isWorkingDay($d)) {
                $lastWorking = $d;
                if (++$found === $n) {
                    return ['date' => $d, 'clamped' => false];
                }
            }
        }

        // mesec bez ijednog radnog dana (ceo mesec odmora) nije realan; poslednji kalendarski dan je bezbedan izbor
        return ['date' => $lastWorking ?? $last, 'clamped' => true];
    }

    /**
     * Neradni dani u opsegu (uključujući oba kraja) sa razlogom.
     *
     * @return array<string, string> Y-m-d => razlog
     */
    public function nonWorkingDaysBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $result = [];
        for ($d = $this->day($from); $d <= $this->day($to); $d = $d->modify('+1 day')) {
            if (null !== ($reason = $this->reason($d))) {
                $result[$d->format('Y-m-d')] = $reason;
            }
        }

        return $result;
    }

    /**
     * Praznici i kolektivni odmor u godini (bez vikenda), za kalendar i flatpickr.
     *
     * @return array<string, array{name: string, type: string}> Y-m-d => naziv i vrsta
     */
    public function specialDays(int $year): array
    {
        return $this->year($year);
    }

    /**
     * @return list<int> ISO dani u nedelji koji su neradni (6 = subota, 7 = nedelja)
     */
    public function weeklyDays(): array
    {
        return $this->weekly ??= $this->cache->get('nwd_weekly_'.$this->version(), fn () => $this->rules->get()->getDays());
    }

    /**
     * Briše keš (poziva se posle svake izmene neradnih dana ili pravila za dane u nedelji).
     */
    public function invalidate(): void
    {
        $this->cache->delete('nwd_version');
        $this->years = [];
        $this->weekly = null;
    }

    /**
     * @return array<string, array{name: string, type: string}>
     */
    private function year(int $year): array
    {
        return $this->years[$year] ??= $this->cache->get(sprintf('nwd_%s_%d', $this->version(), $year), function () use ($year): array {
            $map = [];
            foreach ($this->days->findForYear($year) as $day) {
                foreach ($day->datesInYear($year) as $date) {
                    // praznik ima prednost nad kolektivnim odmorom (naziv praznika je korisniji)
                    if (!isset($map[$date]) || NonWorkingDayType::COLLECTIVE_LEAVE->value === $map[$date]['type']) {
                        $map[$date] = ['name' => (string) $day->getName(), 'type' => $day->getType()->value];
                    }
                }
            }
            ksort($map);

            return $map;
        });
    }

    private function version(): string
    {
        return $this->cache->get('nwd_version', static fn (): string => bin2hex(random_bytes(4)));
    }

    private function walk(\DateTimeInterface $date, string $step): \DateTimeImmutable
    {
        $d = $this->day($date);
        for ($i = 0; $i < 370 && !$this->isWorkingDay($d); ++$i) {
            $d = $d->modify($step);
        }

        return $d;
    }

    private function day(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
