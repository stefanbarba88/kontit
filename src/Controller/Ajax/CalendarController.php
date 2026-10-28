<?php

namespace App\Controller\Ajax;

use App\Service\WorkingDayService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Neradni dani za flatpickr i kalendar (SPEC 8k): dani u nedelji i praznici/kolektivni odmor za godinu.
 */
#[Route('/ajax/calendar')]
class CalendarController extends AbstractAjaxController
{
    #[Route('/non-working', name: 'ajax_calendar_non_working', methods: ['GET'])]
    public function nonWorking(Request $request, WorkingDayService $workingDays): JsonResponse
    {
        $year = max(2000, min(2100, $request->query->getInt('year') ?: (int) date('Y')));

        return $this->ok([
            'year' => $year,
            'weekdays' => $workingDays->weeklyDays(),
            'days' => $workingDays->specialDays($year),
        ]);
    }
}
