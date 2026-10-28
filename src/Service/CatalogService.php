<?php

namespace App\Service;

use App\Entity\JobTemplate;
use App\Entity\JobTemplateChecklistItem;
use App\Entity\ServicePackage;
use App\Entity\User;
use App\Repository\ClientPackageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Katalog poslova i paketa (SPEC 8b).
 */
class CatalogService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientPackageRepository $clientPackages,
        private readonly ClientPackageService $packages,
    ) {
    }

    /**
     * Čuva posao. Stavke checkliste dolaze iz editora (ChecklistCollectionType), pa se samo ponovo numerišu redom.
     */
    public function saveJob(JobTemplate $job): void
    {
        $items = $job->getChecklistItems()->toArray();
        usort($items, static fn (JobTemplateChecklistItem $a, JobTemplateChecklistItem $b) => $a->getPosition() <=> $b->getPosition());
        foreach ($items as $position => $item) {
            $item->setPosition($position);
        }

        $this->em->persist($job);
        $this->em->flush();
    }

    /**
     * Čuva paket. Ako $applyToClients, dodaje nove poslove paketa svim klijentima koji ga imaju.
     * Poslovi uklonjeni iz paketa ostaju klijentima dok se ručno ne ukinu (ne briše se istorija).
     *
     * @return int broj novokreiranih poslova kod klijenata
     */
    public function savePackage(ServicePackage $package, bool $applyToClients, User $actor): int
    {
        $this->em->persist($package);
        $this->em->flush();

        $created = 0;
        if ($applyToClients) {
            foreach ($this->clientPackages->findActiveByPackage($package) as $clientPackage) {
                $created += $this->packages->syncJobs($clientPackage);
            }
        }

        return $created;
    }

    public function countActiveClients(ServicePackage $package): int
    {
        return \count($this->clientPackages->findActiveByPackage($package));
    }
}
