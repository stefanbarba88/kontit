<?php

namespace App\Controller\Ajax;

use App\Entity\Schedule;
use App\Service\ScheduleCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Pregled rasporeda u formi posla (SPEC 8g.3): opis rečima i narednih 5 rokova, sa oznakom pomerenih.
 */
class ScheduleController extends AbstractAjaxController
{
    private const MONTHS_SHORT = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'avg', 'sep', 'okt', 'nov', 'dec'];
    private const DAYS_SHORT = [1 => 'pon', 'uto', 'sre', 'čet', 'pet', 'sub', 'ned'];

    #[Route('/ajax/schedule/preview', name: 'ajax_schedule_preview', methods: ['POST'])]
    public function preview(Request $request, ScheduleCalculator $calculator, ValidatorInterface $validator): JsonResponse
    {
        $data = $this->payload($request);
        $schedule = Schedule::fromArray((array) ($data['schedule'] ?? []));
        if ($schedule->isEmpty()) {
            return $this->ok(['describe' => '', 'occurrences' => []]);
        }
        $errors = $validator->validate($schedule);
        if (\count($errors) > 0) {
            return $this->ok(['describe' => (string) $errors->get(0)->getMessage(), 'occurrences' => [], 'invalid' => true]);
        }

        $anchor = isset($data['anchor']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['anchor']) ? new \DateTimeImmutable((string) $data['anchor']) : null;
        $from = max(new \DateTimeImmutable('today'), $anchor ?? new \DateTimeImmutable('today'));
        $occurrences = [];
        foreach ($calculator->nextOccurrences($schedule, $from, 5, $anchor) as $occ) {
            $occurrences[] = [
                'date' => $this->format($occ['due']),
                'iso' => $occ['due']->format('Y-m-d'),
                'shifted' => $occ['shifted'],
                'tooltip' => $occ['shifted'] ? $this->shiftText($occ['nominal'], $occ['due'], (string) $occ['reason']) : null,
                'period' => $occ['label'],
            ];
        }

        return $this->ok(['describe' => $calculator->describe($schedule), 'short' => $calculator->describe($schedule, true), 'occurrences' => $occurrences]);
    }

    /** "16. nov 2026. (pon)" */
    private function format(\DateTimeImmutable $d): string
    {
        return sprintf('%d. %s %s. (%s)', $d->format('j'), self::MONTHS_SHORT[(int) $d->format('n') - 1], $d->format('Y'), self::DAYS_SHORT[(int) $d->format('N')]);
    }

    /** "15. nov" */
    private function short(\DateTimeImmutable $d): string
    {
        return sprintf('%d. %s', $d->format('j'), self::MONTHS_SHORT[(int) $d->format('n') - 1]);
    }

    /**
     * "15. nov je nedelja, pomereno na 16. nov" ili "11. nov je neradni dan (Dan primirja), pomereno na 12. nov".
     * WorkingDayService za dane u nedelji vraća naziv dana ("Nedelja"), a za ostalo naziv praznika ili odmora.
     */
    private function shiftText(\DateTimeImmutable $nominal, \DateTimeImmutable $due, string $reason): string
    {
        $isWeekday = \in_array($reason, ['Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak', 'Subota', 'Nedelja'], true);

        return sprintf('%s je %s, pomereno na %s', $this->short($nominal), $isWeekday ? mb_strtolower($reason) : 'neradni dan ('.$reason.')', $this->short($due));
    }
}
