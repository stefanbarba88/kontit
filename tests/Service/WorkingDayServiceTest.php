<?php

namespace App\Tests\Service;

use App\Entity\NonWorkingDay;
use App\Entity\WeeklyNonWorkingRule;
use App\Enum\NonWorkingDayType;
use App\Repository\NonWorkingDayRepository;
use App\Repository\WeeklyNonWorkingRuleRepository;
use App\Service\WorkingDayService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class WorkingDayServiceTest extends TestCase
{
    /**
     * Pravi servis nad datim zapisima (ponašanje repozitorijuma: ponavljajući + oni koji seku godinu).
     *
     * @param list<NonWorkingDay> $days
     * @param list<int>           $weekly
     */
    public static function service(array $days, array $weekly = [6, 7]): WorkingDayService
    {
        foreach ($days as $day) {
            $day->normalize();
        }
        $repo = new class($days) extends NonWorkingDayRepository {
            /** @param list<NonWorkingDay> $all */
            public function __construct(private readonly array $all)
            {
            }

            public function findForYear(int $year): array
            {
                return array_values(array_filter($this->all, static fn (NonWorkingDay $d) => $d->isRecurring()
                    || ($d->getDateFrom() <= new \DateTimeImmutable($year.'-12-31') && $d->getDateTo() >= new \DateTimeImmutable($year.'-01-01'))));
            }
        };
        $rule = (new WeeklyNonWorkingRule())->setDays($weekly);
        $rules = new class($rule) extends WeeklyNonWorkingRuleRepository {
            public function __construct(private readonly WeeklyNonWorkingRule $rule)
            {
            }

            public function get(): WeeklyNonWorkingRule
            {
                return $this->rule;
            }
        };

        return new WorkingDayService($repo, $rules, new ArrayAdapter());
    }

    private static function d(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    private function serbia(): WorkingDayService
    {
        return self::service([
            new NonWorkingDay(NonWorkingDayType::HOLIDAY_RECURRING, 'Nova godina', self::d('2020-01-01'), self::d('2020-01-02')),
            new NonWorkingDay(NonWorkingDayType::HOLIDAY_RECURRING, 'Dan primirja', self::d('2020-11-11')),
            new NonWorkingDay(NonWorkingDayType::HOLIDAY_ONCE, 'Uskršnji praznici', self::d('2026-04-10'), self::d('2026-04-13')),
            new NonWorkingDay(NonWorkingDayType::COLLECTIVE_LEAVE, 'Kolektivni odmor', self::d('2026-12-28'), self::d('2027-01-05')),
        ]);
    }

    public function testWeekendAndHolidays(): void
    {
        $s = $this->serbia();
        self::assertTrue($s->isWorkingDay(self::d('2026-10-28')));
        self::assertSame('Subota', $s->reason(self::d('2026-10-31')));
        self::assertSame('Nedelja', $s->reason(self::d('2026-11-01')));
        self::assertSame('Dan primirja', $s->reason(self::d('2026-11-11')), 'praznik koji se ponavlja važi i u drugim godinama');
        self::assertSame('Nova godina', $s->reason(self::d('2031-01-02')));
        self::assertSame('Uskršnji praznici', $s->reason(self::d('2026-04-13')));
        self::assertTrue($s->isWorkingDay(self::d('2027-04-13')), 'praznik samo za jednu godinu ne važi sledeće godine');
    }

    public function testCollectiveLeaveAcrossYearEnd(): void
    {
        $s = $this->serbia();
        self::assertSame('Kolektivni odmor', $s->reason(self::d('2026-12-29')));
        self::assertSame('Nova godina', $s->reason(self::d('2027-01-01')), 'praznik ima prednost nad kolektivnim odmorom');
        self::assertSame('Kolektivni odmor', $s->reason(self::d('2027-01-05')));
        self::assertEquals(self::d('2027-01-06'), $s->nextWorkingDay(self::d('2026-12-28')));
        self::assertEquals(self::d('2026-12-25'), $s->previousWorkingDay(self::d('2027-01-03')));
    }

    public function testNextAndPrevious(): void
    {
        $s = $this->serbia();
        self::assertEquals(self::d('2026-10-28'), $s->nextWorkingDay(self::d('2026-10-28')), 'radni dan ostaje isti');
        self::assertEquals(self::d('2026-11-02'), $s->nextWorkingDay(self::d('2026-10-31')));
        self::assertEquals(self::d('2026-04-14'), $s->nextWorkingDay(self::d('2026-04-10')), 'Uskrs: petak do ponedeljka');
        self::assertEquals(self::d('2026-04-09'), $s->previousWorkingDay(self::d('2026-04-12')));
        self::assertEquals(self::d('2026-11-10'), $s->previousWorkingDay(self::d('2026-11-11')));
    }

    public function testCountAndList(): void
    {
        $s = $this->serbia();
        // april 2026: 22 radna dana bez praznika, minus Veliki petak i Uskršnji ponedeljak
        self::assertSame(20, $s->countWorkingDays(self::d('2026-04-01'), self::d('2026-04-30')));
        self::assertSame(1, $s->countWorkingDays(self::d('2026-10-28'), self::d('2026-10-28')));
        self::assertSame(['2026-11-07' => 'Subota', '2026-11-08' => 'Nedelja', '2026-11-11' => 'Dan primirja'], $s->nonWorkingDaysBetween(self::d('2026-11-07'), self::d('2026-11-11')));
    }

    public function testLeapDayRecurring(): void
    {
        $s = self::service([new NonWorkingDay(NonWorkingDayType::HOLIDAY_RECURRING, 'Prestupni', self::d('2028-02-29'))]);
        self::assertSame('Prestupni', $s->reason(self::d('2032-02-29')));
        self::assertTrue($s->isWorkingDay(self::d('2027-03-01')), '29. februar ne prelazi u 1. mart u neprestupnoj godini');
    }

    /** mart 2027: 6-7. vikend, sreda 10. praznik, 22-26. kolektivni odmor (sa vikendima 20-28. neradni niz) */
    private function overdueService(): WorkingDayService
    {
        return self::service([
            new NonWorkingDay(NonWorkingDayType::HOLIDAY_ONCE, 'Praznik', self::d('2027-03-10')),
            new NonWorkingDay(NonWorkingDayType::COLLECTIVE_LEAVE, 'Kolektivni odmor', self::d('2027-03-22'), self::d('2027-03-26')),
        ]);
    }

    public function testOverdueCutoffIsTodayOnWorkingDaysAndStartOfBlockOtherwise(): void
    {
        $s = $this->overdueService();
        $cutoff = static fn (string $d) => $s->overdueCutoff(self::d($d))->format('Y-m-d');
        self::assertSame('2027-03-05', $cutoff('2027-03-05'), 'petak');
        self::assertSame('2027-03-06', $cutoff('2027-03-06'), 'subota: početak niza');
        self::assertSame('2027-03-06', $cutoff('2027-03-07'), 'nedelja: niz počinje u subotu');
        self::assertSame('2027-03-08', $cutoff('2027-03-08'), 'ponedeljak');
        self::assertSame('2027-03-10', $cutoff('2027-03-10'), 'praznik usred nedelje');
        self::assertSame('2027-03-11', $cutoff('2027-03-11'));
        self::assertSame('2027-03-20', $cutoff('2027-03-24'), 'kolektivni odmor: niz počinje u subotu pre odmora');
        self::assertSame('2027-03-29', $cutoff('2027-03-29'), 'prvi radni dan posle odmora');
    }

    public function testDeadlineOnNonWorkingDayIsOverdueOnlyFromFirstWorkingDayAfter(): void
    {
        $s = $this->overdueService();
        $late = static fn (string $due, string $today) => $s->isOverdue(self::d($due), self::d($today));

        // subota
        self::assertFalse($late('2027-03-06', '2027-03-05'));
        self::assertFalse($late('2027-03-06', '2027-03-06'));
        self::assertFalse($late('2027-03-06', '2027-03-07'), 'u nedelju subotnji rok još ne kasni');
        self::assertTrue($late('2027-03-06', '2027-03-08'), 'kasni od ponedeljka');
        // nedelja
        self::assertFalse($late('2027-03-07', '2027-03-07'));
        self::assertTrue($late('2027-03-07', '2027-03-08'));
        // praznik usred nedelje
        self::assertFalse($late('2027-03-10', '2027-03-10'));
        self::assertTrue($late('2027-03-10', '2027-03-11'), 'kasni od prvog radnog dana posle praznika');
        // kolektivni odmor
        self::assertFalse($late('2027-03-24', '2027-03-28'), 'rok usred odmora ne kasni u odmoru');
        self::assertTrue($late('2027-03-24', '2027-03-29'));
    }

    public function testDeadlineOnWorkingDayKeepsExistingOverdueBehavior(): void
    {
        $s = $this->overdueService();
        self::assertFalse($s->isOverdue(self::d('2027-03-05'), self::d('2027-03-05')), 'rok danas ne kasni');
        self::assertTrue($s->isOverdue(self::d('2027-03-05'), self::d('2027-03-06')), 'petak kasni već u subotu, kao i do sada');
        self::assertTrue($s->isOverdue(self::d('2027-03-05'), self::d('2027-03-07')));
        self::assertTrue($s->isOverdue(self::d('2027-03-09'), self::d('2027-03-10')), 'utorak kasni na praznik');
        self::assertTrue($s->isOverdue(self::d('2027-03-19'), self::d('2027-03-24')), 'petak pre odmora kasni u odmoru');
    }

    public function testCustomWeeklyRule(): void
    {
        $s = self::service([], [7]);
        self::assertTrue($s->isWorkingDay(self::d('2026-10-31')), 'subota je radna ako je pravilo samo nedelja');
        self::assertFalse($s->isWorkingDay(self::d('2026-11-01')));
    }
}
