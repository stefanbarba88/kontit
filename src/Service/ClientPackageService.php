<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\ClientPackage;
use App\Entity\ClientPackagePriceLog;
use App\Entity\JobTemplate;
use App\Entity\Schedule;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\JobSource;
use App\Repository\ClientJobRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ugovorene usluge klijenta (SPEC 8h): dodela paketa sa isključenim poslovima, isključenje, cena, pojedinačni poslovi.
 */
class ClientPackageService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientJobRepository $jobs,
        private readonly TaskService $tasks,
        private readonly ScheduleChangeService $scheduleChanges,
        private readonly ScheduleResolver $resolver,
    ) {
    }

    /**
     * Dodeljuje paket klijentu i pravi poslove iz paketa. Poslovi iz $excluded (id posla => razlog, može prazan)
     * čuvaju se kao isključeni, da se zna da su svesno izostavljeni (SPEC 8h.3).
     *
     * @param array<int, string>   $excluded
     * @param array<int, Schedule> $schedules id posla => prilagođen raspored za ovog klijenta (SPEC 8g.5)
     *
     * @return list<string> upozorenja (npr. posao koji klijent već ima)
     */
    public function attach(ClientPackage $clientPackage, User $actor, array $excluded = [], array $schedules = []): array
    {
        $this->em->wrapInTransaction(function () use ($clientPackage, $actor): void {
            $this->em->persist($clientPackage);
            $this->em->flush();
            $this->em->persist(new ClientPackagePriceLog($clientPackage, null, (string) $clientPackage->getAgreedPrice(), $clientPackage->getStartDate(), $actor));
        });

        $warnings = [];
        $this->syncJobs($clientPackage, $warnings, $excluded, $actor, $schedules);
        $this->em->flush();

        return $warnings;
    }

    /**
     * Pravi poslove koji još ne postoje za klijentov paket. Posao koji klijent već ima (iz drugog paketa
     * ili pojedinačno) se ne dupla, nego se prijavljuje kao upozorenje.
     *
     * @param list<string>       $warnings
     * @param array<int, string>   $excluded id posla => razlog isključenja
     * @param array<int, Schedule> $schedules id posla => prilagođen raspored za ovog klijenta
     *
     * @return int broj novokreiranih poslova
     */
    public function syncJobs(ClientPackage $clientPackage, array &$warnings = [], array $excluded = [], ?User $actor = null, array $schedules = []): int
    {
        $client = $clientPackage->getClient();
        $have = [];
        foreach ($this->jobs->findBy(['client' => $client, 'isActive' => true]) as $job) {
            $have[$job->getJobTemplate()->getId()] = $job;
        }

        $created = 0;
        $custom = [];
        foreach ($clientPackage->getPackage()->getJobTemplates() as $template) {
            if (isset($have[$template->getId()])) {
                if ($have[$template->getId()]->getClientPackage() !== $clientPackage) {
                    // isti posao iz dva paketa: jedan posao, pa jedan zadatak po periodu (SPEC 8i.1)
                    $warnings[] = sprintf('„%s“ klijent već ima (%s), pa nije dupliran. Zadatak za taj posao nastaje jednom po periodu.', $template->getName(), $have[$template->getId()]->getClientPackage()?->getPackage()->getName() ?? $have[$template->getId()]->getSource()->label());
                }
                continue;
            }
            $job = (new ClientJob())
                ->setClient($client)
                ->setJobTemplate($template)
                ->setSource(JobSource::PACKAGE)
                ->setClientPackage($clientPackage)
                ->setStartDate($clientPackage->getStartDate());
            if (null !== $actor && \array_key_exists((int) $template->getId(), $excluded)) {
                $job->exclude($excluded[$template->getId()] ?: 'Isključeno pri dodeli paketa', $actor);
            }
            $this->em->persist($job);
            if (!$job->isExcluded()) {
                $custom[] = [$job, $schedules[$template->getId()] ?? null];
            }
            ++$created;
        }
        $this->em->flush();
        $this->applyCustomSchedules($custom, $actor);

        return $created;
    }

    /**
     * Ukida paket klijentu: završava ga danas, zatvara njegove poslove i (opciono) otkazuje otvorene zadatke.
     *
     * @return int broj otkazanih zadataka
     */
    public function remove(ClientPackage $clientPackage, string $reason, bool $cancelOpenTasks, User $actor): int
    {
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \DomainException('Unesi razlog ukidanja paketa.');
        }

        $cancelled = 0;
        $today = new \DateTimeImmutable('today');
        $this->em->wrapInTransaction(function () use ($clientPackage, $today, $reason, $cancelOpenTasks, $actor, &$cancelled): void {
            $clientPackage->setActive(false)->setEndDate($today);
            foreach ($this->jobs->findBy(['clientPackage' => $clientPackage, 'isActive' => true]) as $job) {
                $job->setActive(false)->setEndDate($today);
                if ($cancelOpenTasks) {
                    $cancelled += $this->cancelOpenTasks($job, 'Ukinut paket: '.$reason, $actor);
                }
            }
        });
        $this->em->flush();

        return $cancelled;
    }

    /**
     * Menja dogovorenu cenu od datuma i beleži istoriju cena.
     */
    public function changePrice(ClientPackage $clientPackage, string $newPrice, \DateTimeImmutable $from, User $actor): void
    {
        $old = $clientPackage->getAgreedPrice();
        if (null !== $old && (float) $old === (float) $newPrice) {
            return;
        }
        $clientPackage->setAgreedPrice($newPrice);
        $this->em->persist(new ClientPackagePriceLog($clientPackage, $old, $newPrice, $from, $actor));
        $this->em->flush();
    }

    /**
     * Isključuje posao iz paketa za ovog klijenta (obavezan razlog); otvoreni zadaci se otkazuju na zahtev.
     *
     * @return int broj otkazanih zadataka
     */
    public function exclude(ClientJob $job, string $reason, bool $cancelOpenTasks, User $actor): int
    {
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \DomainException('Unesi razlog isključenja.');
        }

        $cancelled = 0;
        $this->em->wrapInTransaction(function () use ($job, $reason, $cancelOpenTasks, $actor, &$cancelled): void {
            $job->exclude($reason, $actor);
            if ($cancelOpenTasks) {
                $cancelled = $this->cancelOpenTasks($job, 'Posao isključen: '.$reason, $actor);
            }
        });
        $this->em->flush();

        return $cancelled;
    }

    public function include(ClientJob $job): void
    {
        $job->include();
        $this->em->flush();
    }

    /**
     * Pojedinačni poslovi (SPEC 8h.3b). Admin ih odmah dodaje sa cenom; radnik/šef samo predlaže (bez cene), pa admin odobrava.
     * Posao koji klijent već ima aktivan se ne dupla.
     *
     * @param list<array{template: JobTemplate, price: ?string, note: string, assignee: ?User, schedule?: ?Schedule}> $items
     *
     * @return list<string> upozorenja
     */
    public function addIndividual(Client $client, array $items, \DateTimeImmutable $start, bool $isAdmin, User $actor): array
    {
        $have = [];
        foreach ($this->jobs->findBy(['client' => $client, 'isActive' => true]) as $job) {
            $have[$job->getJobTemplate()->getId()] = true;
        }

        $warnings = [];
        $custom = [];
        foreach ($items as $item) {
            $template = $item['template'];
            if (isset($have[$template->getId()])) {
                $warnings[] = sprintf('„%s“ klijent već ima, pa nije dodat ponovo.', $template->getName());
                continue;
            }
            $job = (new ClientJob())
                ->setClient($client)
                ->setJobTemplate($template)
                ->setSource(JobSource::INDIVIDUAL)
                ->setStartDate($start)
                ->setNote($item['note']);
            if ($isAdmin) {
                $job->setPrice($item['price'])->setAssigneeOverride($item['assignee']);
            } else {
                $job->setProposedBy($actor)->setApproved(false);
            }
            $this->em->persist($job);
            $have[$template->getId()] = true;
            if ($isAdmin) {
                $custom[] = [$job, $item['schedule'] ?? null];
            }
        }
        $this->em->flush();
        $this->applyCustomSchedules($custom, $actor);

        return $warnings;
    }

    /**
     * Prilagođen raspored za tek napravljene poslove (nema zadataka, pa nema preračuna; ide u istoriju klijenta).
     * Raspored koji je prazan ili isti kao podrazumevani se ne čuva, da posao i dalje prati izmene šablona.
     *
     * @param list<array{0: ClientJob, 1: ?Schedule}> $jobs
     */
    private function applyCustomSchedules(array $jobs, ?User $actor): void
    {
        foreach ($jobs as [$job, $schedule]) {
            if (null !== $schedule && !$schedule->isEmpty() && $schedule->toArray() !== $this->resolver->defaultFor($job)->toArray()) {
                $this->scheduleChanges->changeJob($job, $schedule, $actor);
            }
        }
    }

    public function save(ClientJob $job): void
    {
        $this->em->flush();
    }

    /**
     * Iznos iz polja ("1.234,56", "1234.56", "1234") u decimalni string, ili null ako nije ispravan.
     */
    public static function parseMoney(string $value): ?string
    {
        $value = str_replace([' ', "\u{a0}"], '', trim($value));
        if ('' === $value) {
            return null;
        }
        // "1.234,56" -> tačka je za hiljade; "1234.56" -> tačka je decimalna
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }
        if (!is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    public function approve(ClientJob $job, ?string $price): void
    {
        $job->setApproved(true);
        if (null !== $price && '' !== $price) {
            $job->setPrice($price);
        }
        $this->em->flush();
    }

    public function endJob(ClientJob $job, string $reason, bool $cancelOpenTasks, User $actor): int
    {
        $cancelled = 0;
        $this->em->wrapInTransaction(function () use ($job, $reason, $cancelOpenTasks, $actor, &$cancelled): void {
            $job->setActive(false)->setEndDate(new \DateTimeImmutable('today'));
            if ($cancelOpenTasks) {
                $cancelled = $this->cancelOpenTasks($job, 'Posao ukinut: '.$reason, $actor);
            }
        });
        $this->em->flush();

        return $cancelled;
    }

    /**
     * Otvoreni, automatski generisani zadaci posla.
     *
     * @return list<Task>
     */
    public function openTasks(ClientJob $job): array
    {
        return array_values(array_filter(
            $this->em->getRepository(Task::class)->findBy(['clientJob' => $job]),
            static fn (Task $t) => $t->getStatus()->isOpen(),
        ));
    }

    private function cancelOpenTasks(ClientJob $job, string $reason, User $actor): int
    {
        $count = 0;
        foreach ($this->openTasks($job) as $task) {
            $this->tasks->cancel($task, $reason, $actor);
            ++$count;
        }

        return $count;
    }

    public function templateIsUsed(JobTemplate $template): bool
    {
        return null !== $this->jobs->findOneBy(['jobTemplate' => $template]);
    }
}
