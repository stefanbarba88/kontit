<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\ClientPackage;
use App\Entity\User;
use App\Repository\ClientJobRepository;
use App\Repository\ClientPackageRepository;

/**
 * Tab "Ugovorene usluge" na kartici klijenta (SPEC 8h.3): poslovi grupisani po paketima, pa pojedinačni,
 * sa rasporedom, sledećim zadatkom i zaduženim; predlozi koji čekaju odobrenje i završene usluge posebno.
 */
class ContractedServicesOverview
{
    public function __construct(
        private readonly ClientPackageRepository $packages,
        private readonly ClientJobRepository $jobs,
        private readonly ScheduleCalculator $calculator,
        private readonly FinanceService $finance,
    ) {
    }

    /**
     * @return array{
     *     packages: list<array{package: ClientPackage, rows: list<array<string, mixed>>}>,
     *     individual: list<array<string, mixed>>,
     *     proposals: list<array<string, mixed>>,
     *     ended: list<array<string, mixed>>,
     *     endedPackages: list<ClientPackage>,
     *     monthlyTotal: float,
     *     templateIds: list<int>
     * }
     */
    public function build(Client $client): array
    {
        $today = new \DateTimeImmutable('today');
        $byPackage = [];
        $individual = [];
        $proposals = [];
        $ended = [];
        $templateIds = [];

        foreach ($this->jobs->findBy(['client' => $client], ['startDate' => 'ASC', 'id' => 'ASC']) as $job) {
            $row = $this->row($job, $client, $today);
            if (!$job->isApproved()) {
                $proposals[] = $row;
            } elseif (!$job->isActive()) {
                $ended[] = $row;
            } elseif (null !== $job->getClientPackage()) {
                $byPackage[$job->getClientPackage()->getId()][] = $row;
            } else {
                $individual[] = $row;
            }
            if ($job->isActive() && !$job->isExcluded()) {
                $templateIds[] = $job->getJobTemplate()->getId();
            }
        }

        $packages = [];
        $endedPackages = [];
        foreach ($this->packages->findByClient($client) as $cp) {
            if ($cp->isActive()) {
                $rows = $byPackage[$cp->getId()] ?? [];
                // poslovi redom kao u paketu, isključeni na kraju
                $order = array_flip(array_map(static fn ($t) => $t->getId(), $cp->getPackage()->getJobTemplates()));
                usort($rows, static fn (array $a, array $b) => [$a['job']->isExcluded(), $order[$a['job']->getJobTemplate()->getId()] ?? 999] <=> [$b['job']->isExcluded(), $order[$b['job']->getJobTemplate()->getId()] ?? 999]);
                $packages[] = ['package' => $cp, 'rows' => $rows];
            } else {
                $endedPackages[] = $cp;
            }
        }

        return [
            'packages' => $packages,
            'individual' => $individual,
            'proposals' => $proposals,
            'ended' => $ended,
            'endedPackages' => $endedPackages,
            'monthlyTotal' => $this->finance->clientMrr($client),
            'templateIds' => array_values(array_unique($templateIds)),
        ];
    }

    /**
     * @return array{job: ClientJob, describe: string, next: ?\DateTimeImmutable, assignee: ?User, assigneeIsOverride: bool, monthly: ?float}
     */
    private function row(ClientJob $job, Client $client, \DateTimeImmutable $today): array
    {
        $schedule = $job->getSchedule();
        $next = null;
        if ($job->isActive() && $job->isApproved() && !$job->isExcluded() && $schedule->isAutomatic()) {
            $start = max($today, $job->getStartDate() ?? $today);
            $occ = $this->calculator->nextOccurrences($schedule, $start, 1, $job->getStartDate());
            if ([] !== $occ && (null === $job->getEndDate() || $occ[0]['nominal'] <= $job->getEndDate())) {
                $next = $occ[0]['due'];
            }
        }

        return [
            'job' => $job,
            'describe' => $this->calculator->describe($schedule, true),
            'next' => $next,
            'assignee' => $job->getAssigneeOverride() ?? $client->getPrimaryAccountant(),
            'assigneeIsOverride' => null !== $job->getAssigneeOverride(),
            'monthly' => null !== $job->getPrice() ? $this->finance->jobMonthly($job) : null,
        ];
    }
}
