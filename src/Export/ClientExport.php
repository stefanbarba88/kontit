<?php

namespace App\Export;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Service\ClientService;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Izvoz liste klijenata (/clients): isti upit kao ClientController::index i CLIENT_VIEW po redu.
 */
final class ClientExport implements ExportListInterface
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly ClientService $clientService,
        private readonly Security $security,
    ) {
    }

    public function getName(): string
    {
        return 'klijenti';
    }

    public function getTitle(): string
    {
        return 'Klijenti';
    }

    public function isAllowed(User $user): bool
    {
        return $this->security->isGrantedForUser($user, 'ROLE_SEF');
    }

    public function getColumns(): array
    {
        return [
            ['label' => 'Klijent', 'type' => 'text', 'width' => 26],
            ['label' => 'Pun naziv', 'type' => 'text', 'width' => 44],
            ['label' => 'PIB', 'type' => 'text', 'width' => 12],
            ['label' => 'Matični broj', 'type' => 'text', 'width' => 12],
            ['label' => 'PDV', 'type' => 'text', 'width' => 6],
            ['label' => 'Mesto', 'type' => 'text', 'width' => 18],
            ['label' => 'Glavni zaduženi', 'type' => 'text', 'width' => 22],
            ['label' => 'Backup', 'type' => 'text', 'width' => 22],
            ['label' => 'Status', 'type' => 'text', 'width' => 10],
        ];
    }

    public function count(array $query, User $user): int
    {
        return \count(new Paginator($this->queryBuilder($query, $user)));
    }

    public function rows(array $query, User $user): iterable
    {
        foreach ($this->queryBuilder($query, $user)->getQuery()->getResult() as $client) {
            if (!$client instanceof Client || !$this->security->isGrantedForUser($user, 'CLIENT_VIEW', $client)) {
                continue;
            }
            yield [
                $client->getShortName(),
                $client->getName(),
                $client->getPib(),
                $client->getMaticniBroj(),
                $client->isPdvObveznik() ? 'da' : 'ne',
                $client->getCity(),
                $client->getPrimaryAccountant()?->getFullName(),
                $client->getBackupAccountant()?->getFullName(),
                $client->isActive() ? 'aktivan' : 'neaktivan',
            ];
        }
    }

    public function describeFilters(array $query): string
    {
        $parts = [];
        if ('' !== (string) ($query['q'] ?? '')) {
            $parts[] = 'Pretraga: '.$query['q'];
        }
        $parts[] = 'Status: '.match ($query['status'] ?? 'active') {
            'inactive' => 'neaktivni',
            'all', '' => 'svi',
            default => 'aktivni',
        };

        return implode(' · ', $parts);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function queryBuilder(array $query, User $user): QueryBuilder
    {
        return $this->clients->createVisibleQueryBuilder(
            $user,
            $this->clientService->canAssignAnyone($user),
            (string) ($query['q'] ?? ''),
            (string) ($query['status'] ?? 'active'),
        );
    }
}
