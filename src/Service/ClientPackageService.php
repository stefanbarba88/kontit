<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\ClientPackage;
use App\Entity\ClientPackagePriceLog;
use App\Entity\JobTemplate;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\JobSource;
use App\Repository\ClientJobRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Paketi i poslovi klijenta (SPEC 8a/8b): dodela paketa, isključenje posla, cena, ad hoc poslovi.
 */
class ClientPackageService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientJobRepository $jobs,
        private readonly TaskService $tasks,
    ) {
    }

    /**
     * Dodeljuje paket klijentu i pravi poslove iz paketa.
     *
     * @return list<string> upozorenja (npr. posao koji klijent već ima)
     */
    public function attach(ClientPackage $clientPackage, User $actor): array
    {
        $this->em->wrapInTransaction(function () use ($clientPackage, $actor): void {
            $this->em->persist($clientPackage);
            $this->em->flush();
            $this->em->persist(new ClientPackagePriceLog($clientPackage, null, (string) $clientPackage->getAgreedPrice(), $clientPackage->getStartDate(), $actor));
        });

        $warnings = [];
        $this->syncJobs($clientPackage, $warnings);
        $this->em->flush();

        return $warnings;
    }

    /**
     * Pravi poslove koji još ne postoje za klijentov paket. Posao koji klijent već ima (iz drugog paketa
     * ili ad hoc) se ne dupla, nego se prijavljuje kao upozorenje.
     *
     * @param list<string> $warnings
     *
     * @return int broj novokreiranih poslova
     */
    public function syncJobs(ClientPackage $clientPackage, array &$warnings = []): int
    {
        $client = $clientPackage->getClient();
        $have = [];
        foreach ($this->jobs->findBy(['client' => $client, 'isActive' => true]) as $job) {
            $have[$job->getJobTemplate()->getId()] = $job;
        }

        $created = 0;
        foreach ($clientPackage->getPackage()->getJobTemplates() as $template) {
            if (isset($have[$template->getId()])) {
                if ($have[$template->getId()]->getClientPackage() !== $clientPackage) {
                    $warnings[] = sprintf('„%s“ klijent već ima (%s), pa nije dupliran.', $template->getName(), $have[$template->getId()]->getSource()->label());
                }
                continue;
            }
            $job = (new ClientJob())
                ->setClient($client)
                ->setJobTemplate($template)
                ->setSource(JobSource::PACKAGE)
                ->setClientPackage($clientPackage)
                ->setStartDate($clientPackage->getStartDate());
            $this->em->persist($job);
            ++$created;
        }
        $this->em->flush();

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
            throw new \DomainException('Unesite razlog ukidanja paketa.');
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
            throw new \DomainException('Unesite razlog isključenja.');
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
     * Ad hoc posao. Admin ga odmah odobrava; radnik/šef samo predlaže, pa admin odobrava i unosi cenu.
     */
    public function addAdHoc(ClientJob $job, Client $client, bool $isAdmin, User $actor): void
    {
        $job->setClient($client)->setSource(JobSource::AD_HOC);
        if (!$isAdmin) {
            $job->setProposedBy($actor)->setApproved(false)->setPrice(null);
        }
        $this->em->persist($job);
        $this->em->flush();
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
