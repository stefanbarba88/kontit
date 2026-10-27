<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\LegalForm;
use App\Nbs\NbsCompanyLookupService;
use App\Repository\ClientRepository;

/**
 * Uvoz klijenata iz NBS-a po listi PIB-ova. Svaki PIB je zaseban pokušaj: greška jednog ne prekida ostale.
 */
class ClientImportService
{
    public const CREATED = 'created';
    public const EXISTS = 'exists';
    public const FAILED = 'failed';

    /** Najviše PIB-ova u jednom uvozu (svaki je poziv NBS servisa) */
    public const LIMIT = 30;

    public function __construct(
        private readonly NbsCompanyLookupService $nbs,
        private readonly ClientService $clients,
        private readonly ClientRepository $repository,
    ) {
    }

    /**
     * Iz slobodnog teksta (razmak, zarez, novi red) izvlači jedinstvene PIB-ove.
     *
     * @return list<string>
     */
    public function parse(string $text): array
    {
        preg_match_all('/\d+/', $text, $m);

        return array_values(array_unique($m[0]));
    }

    /**
     * @param list<string> $pibs
     *
     * @return list<array{pib: string, status: string, message: string, client: ?Client}>
     */
    public function import(array $pibs, User $primary, ?User $backup, User $actor): array
    {
        if (count($pibs) > self::LIMIT) {
            throw new \DomainException(sprintf('Najviše %d PIB-ova odjednom.', self::LIMIT));
        }

        $results = [];
        foreach ($pibs as $pib) {
            $results[] = $this->importOne($pib, $primary, $backup, $actor);
        }

        return $results;
    }

    /**
     * @return array{pib: string, status: string, message: string, client: ?Client}
     */
    private function importOne(string $pib, User $primary, ?User $backup, User $actor): array
    {
        $existing = $this->repository->findOneBy(['pib' => $pib]);
        if (null !== $existing) {
            return ['pib' => $pib, 'status' => self::EXISTS, 'message' => 'Već postoji: '.$existing->getName(), 'client' => $existing];
        }

        try {
            $data = $this->nbs->lookup($pib);
            $client = (new Client())
                ->setName($data['name'])
                ->setLegalForm(LegalForm::from($data['legalForm']))
                ->setStartDate(new \DateTimeImmutable('today'))
                ->applyNbs($data);
            $this->clients->create($client, $primary, $backup, $actor);

            return ['pib' => $pib, 'status' => self::CREATED, 'message' => $client->getName(), 'client' => $client];
        } catch (\DomainException $e) {
            return ['pib' => $pib, 'status' => self::FAILED, 'message' => $e->getMessage(), 'client' => null];
        }
    }
}
