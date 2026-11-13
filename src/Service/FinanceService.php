<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\Task;
use App\Enum\JobSource;
use App\Enum\PriceUnit;
use App\Enum\ScheduleType;
use App\Enum\TaskStatus;
use App\Repository\ClientJobRepository;
use App\Repository\ClientPackagePriceLogRepository;
use App\Repository\ClientPackageRepository;
use App\Repository\ClientRepository;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finansijska analitika (SPEC 8d), samo za admin+. Sati dolaze iz procena trajanja poslova;
 * Faza 8 (praćenje vremena) menja samo metodu monthlyHours().
 */
class FinanceService
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly ClientPackageRepository $clientPackages,
        private readonly ClientPackagePriceLogRepository $priceLogs,
        private readonly ClientJobRepository $jobs,
        private readonly TeamMemberRepository $teamMembers,
        private readonly SettingsService $settings,
        private readonly CapacityResolver $capacities,
        private readonly ScheduleCalculator $schedule,
        private readonly ScheduleResolver $resolver,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Analitika jednog klijenta.
     *
     * @return array<string, mixed>
     */
    public function clientAnalytics(Client $client): array
    {
        $packages = $this->clientPackages->findByClient($client);
        $jobs = $this->jobs->findBy(['client' => $client]);

        $mrr = $this->clientMrr($client);
        $hours = $this->monthlyHours($client);
        $rate = $hours > 0 ? $mrr / $hours : null;

        $tasks = $this->em->getRepository(Task::class)->findBy(['client' => $client]);
        $since = new \DateTimeImmutable('first day of -2 months midnight');
        $recent = array_filter($tasks, static fn (Task $t) => $t->getCreatedAt() >= $since);
        $done = array_filter($tasks, static fn (Task $t) => TaskStatus::ZAVRSENO === $t->getStatus() && null !== $t->getDueDate());
        $onTime = array_filter($done, static fn (Task $t) => ($t->getApprovedAt() ?? $t->getUpdatedAt()) <= $t->getDueDate()->setTime(23, 59, 59));
        $returned = 0;

        return [
            'mrr' => $mrr,
            'oneTime' => $this->oneTimeRevenue($client),
            'hours' => $hours,
            'rate' => $rate,
            'underpriced' => null !== $rate && $rate < $this->settings->getFloat(SettingsService::MIN_HOURLY_RATE),
            'minRate' => $this->settings->getFloat(SettingsService::MIN_HOURLY_RATE),
            'tasksPerMonth' => round(\count($recent) / 3, 1),
            'onTimePercent' => \count($done) > 0 ? (int) round(100 * \count($onTime) / \count($done)) : null,
            'waiting' => \count(array_filter($tasks, static fn (Task $t) => TaskStatus::CEKA_KLIJENTA === $t->getStatus())),
            'returned' => $returned,
            'packages' => $packages,
            'jobs' => $jobs,
            'priceLog' => $this->priceLogs->findBy(['clientPackage' => $packages], ['effectiveFrom' => 'DESC', 'id' => 'DESC']),
        ];
    }

    /**
     * Mesečni prihod klijenta: aktivni paketi + ponavljajući pojedinačni poslovi sa cenom.
     */
    public function clientMrr(Client $client): float
    {
        $sum = 0.0;
        foreach ($this->clientPackages->findBy(['client' => $client, 'isActive' => true]) as $cp) {
            $sum += (float) $cp->getAgreedPrice();
        }
        foreach ($this->jobs->findBy(['client' => $client, 'isActive' => true, 'isApproved' => true, 'source' => JobSource::INDIVIDUAL]) as $job) {
            $sum += $this->jobMonthly($job);
        }

        return $sum;
    }

    /**
     * Procenjeni mesečni sati rada na klijentu (Faza 8 ovo zamenjuje stvarno evidentiranim vremenom).
     */
    public function monthlyHours(Client $client): float
    {
        $minutes = 0.0;
        foreach ($this->jobs->findBy(['client' => $client, 'isActive' => true, 'isApproved' => true, 'isExcluded' => false]) as $job) {
            $minutes += $job->getEstimatedMinutes() * $this->schedule->perMonth($this->resolver->forClientJob($job));
        }

        return $minutes / 60;
    }

    /**
     * Jednokratni prihod (pojedinačni poslovi "po potrebi" sa cenom, ugovoreni u tekućoj godini).
     */
    public function oneTimeRevenue(Client $client): float
    {
        $sum = 0.0;
        $year = (int) date('Y');
        foreach ($this->jobs->findBy(['client' => $client, 'isApproved' => true, 'source' => JobSource::INDIVIDUAL]) as $job) {
            if (ScheduleType::NONE === $this->resolver->forClientJob($job)->getType() && null !== $job->getPrice() && (int) $job->getStartDate()->format('Y') === $year) {
                $sum += (float) $job->getPrice();
            }
        }

        return $sum;
    }

    /**
     * Pregled cele firme.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $minRate = $this->settings->getFloat(SettingsService::MIN_HOURLY_RATE);
        $clients = $this->clients->findBy(['isActive' => true], ['shortName' => 'ASC']);

        $rows = [];
        $mrrTotal = 0.0;
        $perUser = [];
        foreach ($clients as $client) {
            $mrr = $this->clientMrr($client);
            $hours = $this->monthlyHours($client);
            $rate = $hours > 0 ? $mrr / $hours : null;
            $rows[] = ['client' => $client, 'mrr' => $mrr, 'hours' => $hours, 'rate' => $rate, 'underpriced' => null !== $rate && $rate < $minRate];
            $mrrTotal += $mrr;

            $owner = $client->getPrimaryAccountant();
            if (null !== $owner) {
                $perUser[$owner->getId()] ??= ['user' => $owner, 'mrr' => 0.0, 'hours' => 0.0, 'clients' => 0];
                $perUser[$owner->getId()]['mrr'] += $mrr;
                $perUser[$owner->getId()]['hours'] += $hours;
                ++$perUser[$owner->getId()]['clients'];
            }
        }

        $defaultCapacity = $this->settings->getFloat(SettingsService::DEFAULT_CAPACITY_HOURS);
        foreach ($perUser as &$entry) {
            $entry['capacity'] = $this->capacities->resolve($entry['user']);
            $entry['load'] = $entry['capacity'] > 0 ? (int) round(100 * $entry['hours'] / $entry['capacity']) : null;
        }
        unset($entry);
        usort($perUser, static fn (array $a, array $b) => $b['mrr'] <=> $a['mrr']);

        $byUser = [];
        foreach ($perUser as $entry) {
            $byUser[$entry['user']->getId()] = $entry;
        }
        $perTeam = [];
        foreach ($this->teamMembers->findAllActive() as $member) {
            $entry = $byUser[$member->getUser()->getId()] ?? null;
            if (null === $entry) {
                continue;
            }
            $team = $member->getTeam();
            $perTeam[$team->getId()] ??= ['team' => $team, 'mrr' => 0.0, 'hours' => 0.0, 'clients' => 0];
            $perTeam[$team->getId()]['mrr'] += $entry['mrr'];
            $perTeam[$team->getId()]['hours'] += $entry['hours'];
            $perTeam[$team->getId()]['clients'] += $entry['clients'];
        }

        usort($rows, static fn (array $a, array $b) => ($a['rate'] ?? PHP_FLOAT_MAX) <=> ($b['rate'] ?? PHP_FLOAT_MAX));

        $packageStats = [];
        foreach ($this->clientPackages->findBy(['isActive' => true]) as $cp) {
            if (!$cp->getClient()->isActive()) {
                continue;
            }
            $name = $cp->getPackage()->getName();
            $packageStats[$name] ??= ['name' => $name, 'clients' => 0, 'revenue' => 0.0];
            ++$packageStats[$name]['clients'];
            $packageStats[$name]['revenue'] += (float) $cp->getAgreedPrice();
        }
        usort($packageStats, static fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        return [
            'mrr' => $mrrTotal,
            'clientCount' => \count($clients),
            'rows' => $rows,
            'underpriced' => array_values(array_filter($rows, static fn (array $r) => $r['underpriced'])),
            'perUser' => $perUser,
            'perTeam' => array_values($perTeam),
            'packages' => array_values($packageStats),
            'mrrTrend' => $this->mrrTrend(12),
            'churn' => $this->churn(12),
            'adHocByMonth' => $this->adHocByMonth(12),
            'excluded' => $this->mostExcluded(),
            'heatmap' => $this->heatmap(60),
            'minRate' => $minRate,
            'defaultCapacity' => $defaultCapacity,
        ];
    }

    /**
     * Mesečni MRR za poslednjih $months meseci, iz istorije cena paketa.
     *
     * @return list<array{label: string, mrr: float}>
     */
    public function mrrTrend(int $months): array
    {
        $packages = $this->clientPackages->findAll();
        $logs = [];
        foreach ($this->priceLogs->findBy([], ['effectiveFrom' => 'ASC', 'id' => 'ASC']) as $log) {
            $logs[$log->getClientPackage()->getId()][] = $log;
        }

        $out = [];
        for ($i = $months - 1; $i >= 0; --$i) {
            $start = new \DateTimeImmutable('first day of -'.$i.' months midnight');
            $end = $start->modify('last day of this month');
            $sum = 0.0;
            foreach ($packages as $cp) {
                if ($cp->getStartDate() > $end || (null !== $cp->getEndDate() && $cp->getEndDate() < $start)) {
                    continue;
                }
                $price = (float) $cp->getAgreedPrice();
                foreach ($logs[$cp->getId()] ?? [] as $log) {
                    if ($log->getEffectiveFrom() <= $end) {
                        $price = (float) $log->getNewPrice();
                    }
                }
                $sum += $price;
            }
            $out[] = ['label' => $start->format('m/Y'), 'mrr' => $sum];
        }

        return $out;
    }

    /**
     * Izgubljeni MRR: paketi koji su ukinuti u poslednjih $months meseci, po mesecu ukidanja.
     *
     * @return list<array{label: string, lost: float, count: int}>
     */
    public function churn(int $months): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; --$i) {
            $out[(new \DateTimeImmutable('first day of -'.$i.' months'))->format('Y-m')] = ['label' => (new \DateTimeImmutable('first day of -'.$i.' months'))->format('m/Y'), 'lost' => 0.0, 'count' => 0];
        }
        foreach ($this->clientPackages->findBy(['isActive' => false]) as $cp) {
            $key = $cp->getEndDate()?->format('Y-m');
            if (null !== $key && isset($out[$key])) {
                $out[$key]['lost'] += (float) $cp->getAgreedPrice();
                ++$out[$key]['count'];
            }
        }

        return array_values($out);
    }

    /**
     * Prihod od pojedinačnih poslova po mesecu početka.
     *
     * @return list<array{label: string, revenue: float}>
     */
    public function adHocByMonth(int $months): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; --$i) {
            $d = new \DateTimeImmutable('first day of -'.$i.' months');
            $out[$d->format('Y-m')] = ['label' => $d->format('m/Y'), 'revenue' => 0.0];
        }
        foreach ($this->jobs->findBy(['source' => JobSource::INDIVIDUAL, 'isApproved' => true]) as $job) {
            $key = $job->getStartDate()?->format('Y-m');
            if (null !== $key && null !== $job->getPrice() && isset($out[$key])) {
                $out[$key]['revenue'] += (float) $job->getPrice();
            }
        }

        return array_values($out);
    }

    /**
     * Poslovi koje klijenti najčešće isključuju iz paketa.
     *
     * @return list<array{name: string, count: int}>
     */
    public function mostExcluded(): array
    {
        $counts = [];
        foreach ($this->jobs->findBy(['isExcluded' => true]) as $job) {
            $name = $job->getJobTemplate()->getName();
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        arsort($counts);

        return array_map(static fn (string $name, int $count) => ['name' => $name, 'count' => $count], array_keys($counts), $counts);
    }

    /**
     * Opterećenje po danima (zbir procenjenih minuta poslova sa rokom tog dana) za narednih $days dana.
     *
     * @return array<string, int> datum (Y-m-d) => minuta
     */
    public function heatmap(int $days): array
    {
        $from = new \DateTimeImmutable('today');
        $to = $from->modify('+'.$days.' days');
        $load = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $load[$d->format('Y-m-d')] = 0;
        }
        foreach ($this->jobs->findGeneratable() as $job) {
            foreach ($this->resolver->occurrences($job, $from, $to) as $occ) {
                $load[$occ['due']->format('Y-m-d')] += $job->getEstimatedMinutes();
            }
        }

        return $load;
    }

    /**
     * Za naplatu u mesecu: paket klijenta + pojedinačni poslovi sa cenom koji počinju u tom mesecu.
     *
     * @return list<array{client: Client, package: float, adHoc: float, total: float, items: list<string>}>
     */
    public function billing(\DateTimeImmutable $month): array
    {
        $start = $month->modify('first day of this month midnight');
        $end = $start->modify('last day of this month');
        $rows = [];

        foreach ($this->clientPackages->findAll() as $cp) {
            if ($cp->getStartDate() > $end || (null !== $cp->getEndDate() && $cp->getEndDate() < $start) || !$cp->getClient()->isActive()) {
                continue;
            }
            $id = $cp->getClient()->getId();
            $rows[$id] ??= ['client' => $cp->getClient(), 'package' => 0.0, 'adHoc' => 0.0, 'total' => 0.0, 'items' => []];
            $rows[$id]['package'] += (float) $cp->getAgreedPrice();
            $rows[$id]['items'][] = $cp->getPackage()->getName();
        }
        foreach ($this->jobs->findBy(['source' => JobSource::INDIVIDUAL, 'isApproved' => true]) as $job) {
            if (null === $job->getPrice() || $job->getStartDate() < $start || $job->getStartDate() > $end) {
                continue;
            }
            $id = $job->getClient()->getId();
            $rows[$id] ??= ['client' => $job->getClient(), 'package' => 0.0, 'adHoc' => 0.0, 'total' => 0.0, 'items' => []];
            $rows[$id]['adHoc'] += (float) $job->getPrice();
            $rows[$id]['items'][] = $job->getJobTemplate()->getName();
        }
        foreach ($rows as &$row) {
            $row['total'] = $row['package'] + $row['adHoc'];
        }
        unset($row);
        usort($rows, static fn (array $a, array $b) => strcmp((string) $a['client']->getShortName(), (string) $b['client']->getShortName()));

        return $rows;
    }

    /**
     * Mesečna vrednost pojedinačnog posla po jedinici cene (mesečno, godišnje, po izvršenju x učestalost).
     */
    public function jobMonthly(ClientJob $job): float
    {
        if (null === $job->getPrice()) {
            return 0.0;
        }
        $template = $job->getJobTemplate();
        $price = (float) $job->getPrice();

        return match ($template->getPriceUnit()) {
            PriceUnit::MESECNO => $price,
            PriceUnit::GODISNJE => $price / 12,
            PriceUnit::PO_IZVRSENJU => $price * $this->schedule->perMonth($this->resolver->forClientJob($job)),
        };
    }
}
