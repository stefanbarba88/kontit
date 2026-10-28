<?php

namespace App\Tests\Service;

use App\Entity\NonWorkingDay;
use App\Enum\NonWorkingDayType;
use App\Service\ApprovalDeadlineCalculator;
use PHPUnit\Framework\TestCase;

class ApprovalDeadlineCalculatorTest extends TestCase
{
    private ApprovalDeadlineCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new ApprovalDeadlineCalculator(WorkingDayServiceTest::service([
            new NonWorkingDay(NonWorkingDayType::HOLIDAY_RECURRING, 'Dan primirja', new \DateTimeImmutable('2020-11-11')),
        ]));
    }

    private static function dt(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s);
    }

    public function testWorkingHoursSkipWeekend(): void
    {
        // petak 15:00 + 48 radnih sati: petak 9 h, ponedeljak 24 h, utorak 15 h -> utorak 15:00
        self::assertEquals(self::dt('2026-11-03 15:00'), $this->calc->deadline(self::dt('2026-10-30 15:00'), 48));
    }

    public function testSkipsHoliday(): void
    {
        // utorak 10.11. 12:00 + 48 h: utorak 12 h, (sreda 11.11. praznik), četvrtak 24 h, petak 12 h -> petak 12:00
        self::assertEquals(self::dt('2026-11-13 12:00'), $this->calc->deadline(self::dt('2026-11-10 12:00'), 48));
    }

    public function testSubmittedOnWeekendStartsMonday(): void
    {
        // subota: računa se od ponedeljka 00:00, ceo ponedeljak i utorak -> sreda 00:00
        self::assertEquals(self::dt('2026-11-04 00:00'), $this->calc->deadline(self::dt('2026-10-31 10:00'), 48));
    }

    public function testClampedToDayBeforeStart(): void
    {
        $deadline = $this->calc->deadline(self::dt('2026-10-28 09:00'), 48, self::dt('2026-10-29'));
        self::assertEquals(self::dt('2026-10-28 23:59:59'), $deadline);
        self::assertTrue($this->calc->isUrgent(self::dt('2026-10-28 09:00'), $deadline, 48));
        self::assertFalse($this->calc->isUrgent(self::dt('2026-10-28 09:00'), $this->calc->deadline(self::dt('2026-10-28 09:00'), 48, self::dt('2026-11-20')), 48));
    }
}
