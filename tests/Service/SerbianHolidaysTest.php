<?php

namespace App\Tests\Service;

use App\Service\SerbianHolidays;
use PHPUnit\Framework\TestCase;

class SerbianHolidaysTest extends TestCase
{
    public function testOrthodoxEaster(): void
    {
        foreach (['2023' => '2023-04-16', '2024' => '2024-05-05', '2025' => '2025-04-20', '2026' => '2026-04-12', '2027' => '2027-05-02', '2030' => '2030-04-28'] as $year => $date) {
            self::assertSame($date, SerbianHolidays::orthodoxEaster((int) $year)->format('Y-m-d'), (string) $year);
        }
    }

    /**
     * @return array<string, string> Y-m-d => naziv (samo pomeranja zbog nedelje)
     */
    private function shifts(int $year): array
    {
        $out = [];
        foreach (SerbianHolidays::forYear($year) as $item) {
            if ($item['sundayShift']) {
                $out[$item['from']->format('Y-m-d')] = $item['name'];
            }
        }

        return $out;
    }

    public function testSundayShift(): void
    {
        // 2026: 15. februar je nedelja, 16. je već praznik, pa se ne radi u utorak 17.
        self::assertSame(['2026-02-17'], array_keys($this->shifts(2026)));
        // 2023: 1. januar je nedelja, 2. je praznik, pa je slobodan utorak 3. januar
        self::assertSame(['2023-01-03'], array_keys($this->shifts(2023)));
        // 2027: 2. maj je nedelja (i Vaskrs), ponedeljak 3. je Vaskršnji ponedeljak, pa je slobodan utorak 4. maj
        self::assertSame(['2027-05-04'], array_keys($this->shifts(2027)));
    }

    public function testEasterRange(): void
    {
        $easter = array_values(array_filter(SerbianHolidays::forYear(2026), static fn (array $i) => str_starts_with($i['name'], 'Uskršnji')))[0];
        self::assertSame('2026-04-10', $easter['from']->format('Y-m-d'));
        self::assertSame('2026-04-13', $easter['to']->format('Y-m-d'));
        self::assertCount(5 + 1 + 1, SerbianHolidays::forYear(2026));
    }
}
