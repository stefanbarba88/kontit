<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientJobRepository;

/**
 * Ljudi raspoređeni na klijenta (SPEC 8i.3): glavni radnik, zamena i zaduženi postavljeni na nekom njegovom poslu.
 * Samo njima se dodeljuje zadatak tog klijenta; server ovo uvek ponovo proverava.
 */
class ClientPeople
{
    public function __construct(private readonly ClientJobRepository $jobs)
    {
    }

    /**
     * @return array<int, array{user: User, role: string}> id korisnika => korisnik i uloga ("glavni", "zamena", "na poslu: ...")
     */
    public function of(Client $client): array
    {
        $people = [];
        if (null !== ($primary = $client->getPrimaryAccountant())) {
            $people[$primary->getId()] = ['user' => $primary, 'role' => 'glavni'];
        }
        if (null !== ($backup = $client->getBackupAccountant())) {
            $people[$backup->getId()] ??= ['user' => $backup, 'role' => 'zamena'];
        }
        foreach ($this->jobs->findBy(['client' => $client, 'isActive' => true, 'isExcluded' => false]) as $job) {
            $user = $job->getAssigneeOverride();
            if (null !== $user) {
                $people[$user->getId()] ??= ['user' => $user, 'role' => 'na poslu: '.$job->getJobTemplate()->getName()];
            }
        }

        return array_filter($people, static fn (array $p) => $p['user']->isActive());
    }

    public function isAssigned(Client $client, User $user): bool
    {
        return isset($this->of($client)[$user->getId()]);
    }
}
