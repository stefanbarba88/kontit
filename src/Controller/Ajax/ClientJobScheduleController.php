<?php

namespace App\Controller\Ajax;

use App\Entity\ClientJob;
use App\Service\ScheduleCalculator;
use App\Service\ScheduleChangeService;
use App\Service\ScheduleResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Prilagođen raspored posla za klijenta (SPEC 8g.5): pregled uticaja na otvorene zadatke pa primena, i vraćanje na podrazumevani.
 * Pravo imaju oni koji upravljaju poslovima klijenta (CATALOG_MANAGE), isto kao izmena posla i paketa.
 * Telo: { "schedule": { ...polja rasporeda } } ili { "schedule": null } za vraćanje na podrazumevani.
 */
#[IsGranted('CATALOG_MANAGE')]
class ClientJobScheduleController extends AbstractAjaxController
{
    public function __construct(
        private readonly ScheduleChangeService $changes,
        private readonly ScheduleResolver $resolver,
        private readonly ScheduleCalculator $calculator,
    ) {
    }

    #[Route('/ajax/client-jobs/{id}/schedule/impact', name: 'ajax_client_job_schedule_impact', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function impact(Request $request, ClientJob $job): JsonResponse
    {
        $schedule = $this->requested($request, $job);
        if ($schedule instanceof JsonResponse) {
            return $schedule;
        }

        return $this->ok($this->changes->jobImpact($job, $schedule['effective'])->toArray() + $this->summary($job, $schedule['effective'], $schedule['override']));
    }

    #[Route('/ajax/client-jobs/{id}/schedule', name: 'ajax_client_job_schedule', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function change(Request $request, ClientJob $job): JsonResponse
    {
        $schedule = $this->requested($request, $job);
        if ($schedule instanceof JsonResponse) {
            return $schedule;
        }

        $impact = $this->changes->changeJob($job, $schedule['override'], $this->actor());
        $message = $schedule['override']->isEmpty() ? 'Vraćen je podrazumevani raspored.' : 'Raspored za klijenta je sačuvan.';
        if ($impact->movedCount() > 0) {
            $message .= sprintf(' Rok je promenjen na %d otvorenih zadataka.', $impact->movedCount());
        }

        return $this->ok($impact->toArray(), $message);
    }

    /**
     * @return array{override: \App\Entity\Schedule, effective: \App\Entity\Schedule}|JsonResponse
     */
    private function requested(Request $request, ClientJob $job): array|JsonResponse
    {
        $raw = $this->payload($request)['schedule'] ?? null;
        $override = null === $raw ? new \App\Entity\Schedule() : $this->changes->parse($raw);
        if (null === $override) {
            return $this->fail('Raspored nije ispravan.', 422);
        }

        return ['override' => $override, 'effective' => $override->isEmpty() ? $this->resolver->defaultFor($job) : $override];
    }

    /**
     * @return array{describe: string, customized: bool}
     */
    private function summary(ClientJob $job, \App\Entity\Schedule $effective, \App\Entity\Schedule $override): array
    {
        return ['describe' => $this->calculator->describe($effective, true), 'customized' => !$override->isEmpty()];
    }
}
