<?php

namespace App\Service;

use App\Entity\NonWorkingDay;
use App\Enum\NonWorkingDayType;
use App\Repository\NonWorkingDayRepository;
use App\Repository\WeeklyNonWorkingRuleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Izmene neradnih dana (SPEC 8k). Posle svake izmene briše keš WorkingDayService-a.
 */
class NonWorkingDayService
{
    public function __construct(
        private readonly NonWorkingDayRepository $days,
        private readonly WeeklyNonWorkingRuleRepository $rules,
        private readonly WorkingDayService $workingDays,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function save(NonWorkingDay $day): void
    {
        $this->em->persist($day);
        $this->em->flush();
        $this->workingDays->invalidate();
    }

    public function delete(NonWorkingDay $day): void
    {
        $this->em->remove($day);
        $this->em->flush();
        $this->workingDays->invalidate();
    }

    /**
     * @param list<int> $days ISO dani u nedelji
     */
    public function saveWeekly(array $days): void
    {
        $rule = $this->rules->get()->setDays($days);
        $this->em->persist($rule);
        $this->em->flush();
        $this->workingDays->invalidate();
    }

    /**
     * Predlog zakonskih praznika za godinu, uz oznaku šta već postoji.
     *
     * @return list<array{type: NonWorkingDayType, name: string, from: \DateTimeImmutable, to: \DateTimeImmutable, sundayShift: bool, exists: bool}>
     */
    public function proposal(int $year): array
    {
        $existing = $this->days->findForYear($year);
        $out = [];
        foreach (SerbianHolidays::forYear($year) as $item) {
            $out[] = $item + ['exists' => $this->covered($existing, $item, $year)];
        }

        return $out;
    }

    /**
     * @param list<array{type: NonWorkingDayType, name: string, from: \DateTimeImmutable, to: \DateTimeImmutable, exists: bool}> $items
     */
    public function saveProposal(array $items): int
    {
        $created = 0;
        foreach ($items as $item) {
            if ($item['exists']) {
                continue;
            }
            $this->em->persist(new NonWorkingDay($item['type'], $item['name'], $item['from'], $item['to']));
            ++$created;
        }
        $this->em->flush();
        $this->workingDays->invalidate();

        return $created;
    }

    /**
     * Predlog već postoji ako su svi njegovi datumi već neradni zbog postojećih praznika.
     *
     * @param list<NonWorkingDay>                                       $existing
     * @param array{from: \DateTimeImmutable, to: \DateTimeImmutable} $item
     */
    private function covered(array $existing, array $item, int $year): bool
    {
        $dates = [];
        foreach ($existing as $day) {
            if (NonWorkingDayType::COLLECTIVE_LEAVE !== $day->getType()) {
                $dates += array_flip($day->datesInYear($year));
            }
        }
        for ($d = $item['from']; $d <= $item['to']; $d = $d->modify('+1 day')) {
            if (!isset($dates[$d->format('Y-m-d')])) {
                return false;
            }
        }

        return true;
    }
}
