<?php

namespace App\Tests\Service;

use App\Entity\NonWorkingDay;
use App\Entity\Schedule;
use App\Enum\NonWorkingDayShift;
use App\Enum\NonWorkingDayType;
use App\Enum\ScheduleType;
use App\Service\ScheduleCalculator;
use PHPUnit\Framework\TestCase;

class ScheduleCalculatorTest extends TestCase
{
    private ScheduleCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new ScheduleCalculator(WorkingDayServiceTest::service([
            new NonWorkingDay(NonWorkingDayType::HOLIDAY_RECURRING, 'Nova godina', new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-02')),
            new NonWorkingDay(NonWorkingDayType::HOLIDAY_RECURRING, 'Dan primirja', new \DateTimeImmutable('2020-11-11')),
            new NonWorkingDay(NonWorkingDayType::COLLECTIVE_LEAVE, 'Kolektivni odmor', new \DateTimeImmutable('2026-12-28'), new \DateTimeImmutable('2027-01-05')),
        ]));
    }

    private static function s(ScheduleType $type, array $fields = []): Schedule
    {
        return Schedule::fromArray($fields + ['type' => $type->value, 'nonWorkingDayShift' => 'NEXT', 'leadDays' => 10]);
    }

    /**
     * @param list<array{due: \DateTimeImmutable}> $occ
     *
     * @return list<string>
     */
    private static function dues(array $occ): array
    {
        return array_map(static fn (array $o) => $o['due']->format('Y-m-d'), $occ);
    }

    public function testMonthly31InFebruaryAndLeapYear(): void
    {
        $s = self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 31]);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2027-01-07'), 3);
        // (od 7.1, posle kolektivnog odmora) 31.1.2027 je nedelja -> 1.2; februar nema 31. -> 28.2.2027 (nedelja) -> 1.3; 31.3. sreda
        self::assertSame(['2027-02-01', '2027-03-01', '2027-03-31'], self::dues($occ));
        self::assertSame(['2027-01', '2027-02', '2027-03'], array_column($occ, 'key'), 'ključ je mesec nominalnog roka, i kad se rok pomeri u sledeći mesec');
        $leap = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2028-02-01'), 1);
        self::assertSame('2028-02-29', $leap[0]['due']->format('Y-m-d'), 'prestupna godina: 29. februar (utorak)');
    }

    public function testLastDayOfMonth(): void
    {
        $s = self::s(ScheduleType::MONTHLY, ['dayOfMonth' => Schedule::LAST_DAY, 'nonWorkingDayShift' => 'PREVIOUS']);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2026-04-01'), 3);
        // 30.4. četvrtak, 31.5. nedelja -> petak 29.5, 30.6. utorak
        self::assertSame(['2026-04-30', '2026-05-29', '2026-06-30'], self::dues($occ));
        self::assertTrue($occ[1]['shifted']);
        self::assertSame('Nedelja', $occ[1]['reason']);
    }

    public function testNthWorkdayNovember2026(): void
    {
        // novembar 2026: 11.11. (sreda) je praznik; vikendi 1,7,8,14,15,21,22,28,29. 20. radni dan je 30.11.
        $s = self::s(ScheduleType::NTH_WORKDAY, ['nthWorkingDay' => 20]);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-30', $occ[0]['due']->format('Y-m-d'));
        self::assertFalse($occ[0]['clamped']);
        self::assertFalse($occ[0]['shifted']);
        self::assertSame('2026-11', $occ[0]['key']);
        self::assertSame('20. radni dan u mesecu', $this->calc->describe($s));
    }

    public function testNthWorkdaySkipsWeekendAndHoliday(): void
    {
        // 1. radni dan novembra 2026 je ponedeljak 2.11. (1.11. je nedelja)
        $first = $this->calc->nextOccurrences(self::s(ScheduleType::NTH_WORKDAY, ['nthWorkingDay' => 1]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-02', $first[0]['due']->format('Y-m-d'));
        // radni dani: 2,3,4,5,6,9,10 (7. je 10.11.), pa 11.11. praznik se preskače i 8. radni dan je 12.11.
        $seventh = $this->calc->nextOccurrences(self::s(ScheduleType::NTH_WORKDAY, ['nthWorkingDay' => 7]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-10', $seventh[0]['due']->format('Y-m-d'));
        $eighth = $this->calc->nextOccurrences(self::s(ScheduleType::NTH_WORKDAY, ['nthWorkingDay' => 8]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-12', $eighth[0]['due']->format('Y-m-d'), 'praznik 11.11. se ne broji');
    }

    public function testNthWorkdayMonthWithTooFewWorkingDays(): void
    {
        // novembar 2026 ima 20 radnih dana: 23. radni dan se svodi na poslednji (30.11.) uz clamped
        $occ = $this->calc->nextOccurrences(self::s(ScheduleType::NTH_WORKDAY, ['nthWorkingDay' => 23]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-30', $occ[0]['due']->format('Y-m-d'));
        self::assertTrue($occ[0]['clamped']);
    }

    public function testNthWorkdayAcrossMonthAndYear(): void
    {
        $s = self::s(ScheduleType::NTH_WORKDAY, ['nthWorkingDay' => 3]);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2026-11-20'), 3);
        // dec 2026: 1,2,3 => 3.12.; jan 2027: 1-2.1. praznici, 3.1. nedelja, odmor do 5.1. => 6,7,8 => 8.1.
        self::assertSame(['2026-12-03', '2027-01-08', '2027-02-03'], self::dues($occ));
    }

    public function testNthWorkdayRespectsAnchor(): void
    {
        $s = self::s(ScheduleType::NTH_WORKDAY, ['nthWorkingDay' => 1]);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2026-11-01'), 2, new \DateTimeImmutable('2026-11-10'));
        self::assertSame(['2026-12-01', '2027-01-06'], self::dues($occ), 'novembarski rok (2.11.) je pre početka posla');
    }

    public function testWorkdayFromEnd(): void
    {
        // 1 = poslednji radni dan; kolektivni odmor 28.12.-5.1. se ne računa
        $last = self::s(ScheduleType::WORKDAY_END, ['workingDayFromEnd' => 1]);
        $occ = $this->calc->nextOccurrences($last, new \DateTimeImmutable('2026-11-01'), 3);
        // novembar: 30.11. ponedeljak; decembar 2026: 31.12. je u kolektivnom odmoru, poslednji radni je 25.12. (petak);
        // januar 2027: 29.1. petak
        self::assertSame(['2026-11-30', '2026-12-25', '2027-01-29'], self::dues($occ));
        self::assertSame('Poslednji radni dan u mesecu', $this->calc->describe($last));
        self::assertSame('2026-11', $occ[0]['key']);
    }

    public function testWorkdayFromEndSecondToLastAndDecemberWithoutLeave(): void
    {
        $second = self::s(ScheduleType::WORKDAY_END, ['workingDayFromEnd' => 2]);
        $occ = $this->calc->nextOccurrences($second, new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-27', $occ[0]['due']->format('Y-m-d'), 'predposlednji radni dan novembra (petak)');
        self::assertSame('2. radni dan od kraja meseca', $this->calc->describe($second));

        // bez kolektivnog odmora: 31.12.2026. je četvrtak = poslednji radni dan decembra
        $plain = new ScheduleCalculator(WorkingDayServiceTest::service([]));
        $dec = $plain->nextOccurrences(self::s(ScheduleType::WORKDAY_END, ['workingDayFromEnd' => 1]), new \DateTimeImmutable('2026-12-01'), 1);
        self::assertSame('2026-12-31', $dec[0]['due']->format('Y-m-d'));
    }

    public function testWorkdayFromEndTooFewWorkingDays(): void
    {
        // novembar 2026 ima 20 radnih dana: 23. od kraja svodi se na prvi radni dan (2.11.) uz clamped
        $occ = $this->calc->nextOccurrences(self::s(ScheduleType::WORKDAY_END, ['workingDayFromEnd' => 23]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-02', $occ[0]['due']->format('Y-m-d'));
        self::assertTrue($occ[0]['clamped']);
        $exact = $this->calc->nextOccurrences(self::s(ScheduleType::WORKDAY_END, ['workingDayFromEnd' => 20]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-02', $exact[0]['due']->format('Y-m-d'), '20. od kraja je tačno prvi radni dan, bez upozorenja');
        self::assertFalse($exact[0]['clamped']);
    }

    public function testNthWorkdayValidation(): void
    {
        self::assertSame(23, Schedule::fromArray(['type' => 'NTH_WORKDAY', 'nthWorkingDay' => '23'])->getNthWorkingDay());
        self::assertSame(20, Schedule::fromArray(['type' => 'NTH_WORKDAY', 'nthWorkingDay' => 20])->toArray()['nthWorkingDay']);
    }

    public function testExactDayKeepsNonWorkingDate(): void
    {
        $exact = ['nonWorkingDayShift' => 'EXACT'];
        // 15.11.2026. je nedelja, 11.11. praznik, 14.11. subota
        $sunday = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 15] + $exact), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-15', $sunday[0]['due']->format('Y-m-d'));
        self::assertFalse($sunday[0]['shifted']);
        self::assertSame('Nedelja', $sunday[0]['reason'], 'razlog ostaje za upozorenje u previewu');
        $saturday = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 14] + $exact), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-14', $saturday[0]['due']->format('Y-m-d'));
        $holiday = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 11] + $exact), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-11', $holiday[0]['due']->format('Y-m-d'));
        self::assertSame('Dan primirja', $holiday[0]['reason']);
        $working = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 17] + $exact), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertNull($working[0]['reason']);
        self::assertSame('2026-11', $sunday[0]['key'], 'ključ perioda isti kao kod pomeranja');
        self::assertStringContainsString('tačno tog dana', $this->calc->describe(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 15] + $exact)));
    }

    public function testWeekendAndHolidayShift(): void
    {
        $next = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 15]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-16', $next[0]['due']->format('Y-m-d'), '15.11.2026. je nedelja -> ponedeljak');
        $prev = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 15, 'nonWorkingDayShift' => 'PREVIOUS']), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-13', $prev[0]['due']->format('Y-m-d'), 'prethodni radni dan: petak');
        $holiday = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 11]), new \DateTimeImmutable('2026-11-01'), 1);
        self::assertSame('2026-11-12', $holiday[0]['due']->format('Y-m-d'), '11. novembar je praznik');
    }

    public function testCollectiveLeaveAcrossMonthEnd(): void
    {
        $s = self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 31]);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2026-12-01'), 1);
        self::assertSame('2027-01-06', $occ[0]['due']->format('Y-m-d'), 'kolektivni odmor 28.12-5.1 i Nova godina: prvi radni dan je 6. januar');
        self::assertSame('2026-12', $occ[0]['key']);
        $prev = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 31, 'nonWorkingDayShift' => 'PREVIOUS']), new \DateTimeImmutable('2026-12-01'), 1);
        self::assertSame('2026-12-25', $prev[0]['due']->format('Y-m-d'));
    }

    public function testDailyWorkingDaysOnly(): void
    {
        $s = self::s(ScheduleType::DAILY, ['interval' => 1, 'workingDaysOnly' => true, 'leadDays' => 0]);
        $occ = $this->calc->occurrencesBetween($s, new \DateTimeImmutable('2026-11-06'), new \DateTimeImmutable('2026-11-13'));
        // petak 6, (vikend), 9, 10, (11 praznik), 12, 13
        self::assertSame(['2026-11-06', '2026-11-09', '2026-11-10', '2026-11-12', '2026-11-13'], self::dues($occ));
        self::assertSame('2026-11-09', $occ[1]['key']);
        $all = $this->calc->occurrencesBetween(self::s(ScheduleType::DAILY, ['interval' => 1, 'workingDaysOnly' => false]), new \DateTimeImmutable('2026-11-06'), new \DateTimeImmutable('2026-11-08'));
        self::assertCount(3, $all, 'bez "samo radni dani" dnevni posao ide i vikendom');
    }

    public function testQuarterAcrossYearEnd(): void
    {
        $s = self::s(ScheduleType::QUARTERLY, ['dayOfMonth' => 20, 'monthOfQuarter' => 1]);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2026-10-21'), 2);
        // Q4 2026: 20.1.2027 (sreda); Q1 2027: 20.4.2027 (utorak)
        self::assertSame(['2027-01-20', '2027-04-20'], self::dues($occ));
        self::assertSame(['2026-Q4', '2027-Q1'], array_column($occ, 'key'));
        self::assertSame('Q4 2026', $occ[0]['label']);
        $second = $this->calc->nextOccurrences(self::s(ScheduleType::QUARTERLY, ['dayOfMonth' => 15, 'monthOfQuarter' => 2]), new \DateTimeImmutable('2027-01-01'), 1);
        self::assertSame(['2027-02-15'], self::dues($second));
        self::assertSame('2026-Q4', $second[0]['key'], 'drugi mesec posle kvartala i dalje pripada istom kvartalu');
    }

    public function testWeeklyAndIntervals(): void
    {
        $weekly = $this->calc->nextOccurrences(self::s(ScheduleType::WEEKLY, ['dayOfWeek' => 1, 'interval' => 2]), new \DateTimeImmutable('2026-11-01'), 3, new \DateTimeImmutable('2026-10-26'));
        // na svake 2 nedelje od nedelje 26.10: 9.11, 23.11, 7.12
        self::assertSame(['2026-11-09', '2026-11-23', '2026-12-07'], self::dues($weekly));
        self::assertSame('2026-W46', $weekly[0]['key']);
        $monthly = $this->calc->nextOccurrences(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 10, 'interval' => 3]), new \DateTimeImmutable('2026-11-01'), 2, new \DateTimeImmutable('2026-09-01'));
        self::assertSame(['2026-12-10', '2027-03-10'], self::dues($monthly), 'na svaka 3 meseca od septembra');
        $yearly = $this->calc->nextOccurrences(self::s(ScheduleType::YEARLY, ['month' => 6, 'dayOfMonth' => 30]), new \DateTimeImmutable('2026-07-01'), 1);
        self::assertSame(['2027-06-30'], self::dues($yearly));
        self::assertSame('2027', $yearly[0]['key']);
    }

    public function testAnchorAndNone(): void
    {
        $s = self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 15]);
        $occ = $this->calc->nextOccurrences($s, new \DateTimeImmutable('2026-01-01'), 1, new \DateTimeImmutable('2026-03-20'));
        self::assertSame('2026-04-15', $occ[0]['due']->format('Y-m-d'), 'pre početka posla nema rokova');
        self::assertSame([], $this->calc->nextOccurrences(self::s(ScheduleType::NONE), new \DateTimeImmutable('2026-01-01'), 5));
    }

    public function testDescribe(): void
    {
        self::assertSame('Mesečno, 15. u mesecu (ako je neradni dan, prvi sledeći radni dan)', $this->calc->describe(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 15])));
        self::assertSame('Mesečno, 15.', $this->calc->describe(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 15]), true));
        self::assertSame('Svakog radnog dana', $this->calc->describe(self::s(ScheduleType::DAILY, ['interval' => 1, 'workingDaysOnly' => true])));
        self::assertSame('Svakog ponedeljka (ako je neradni dan, prethodni radni dan)', $this->calc->describe(self::s(ScheduleType::WEEKLY, ['dayOfWeek' => 1, 'nonWorkingDayShift' => NonWorkingDayShift::PREVIOUS->value])));
        self::assertSame('Na svaka 2 meseca, poslednji dan u mesecu (ako je neradni dan, prvi sledeći radni dan)', $this->calc->describe(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => -1, 'interval' => 2])));
        self::assertSame('Kvartalno, 20. u 1. mesecu posle kvartala (ako je neradni dan, prvi sledeći radni dan)', $this->calc->describe(self::s(ScheduleType::QUARTERLY, ['dayOfMonth' => 20, 'monthOfQuarter' => 1])));
        self::assertSame('Godišnje, 30. jun (ako je neradni dan, prvi sledeći radni dan)', $this->calc->describe(self::s(ScheduleType::YEARLY, ['month' => 6, 'dayOfMonth' => 30])));
        self::assertSame('Po potrebi', $this->calc->describe(self::s(ScheduleType::NONE), true));
        self::assertSame('Na svakih 5 meseci, 1. u mesecu (ako je neradni dan, prvi sledeći radni dan)', $this->calc->describe(self::s(ScheduleType::MONTHLY, ['dayOfMonth' => 1, 'interval' => 5])));
    }
}
