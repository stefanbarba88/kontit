<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ClientAssignmentLog;
use App\Enum\AssignmentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientAssignmentLog>
 */
class ClientAssignmentLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientAssignmentLog::class);
    }

    public function findCurrent(Client $client, AssignmentType $type): ?ClientAssignmentLog
    {
        return $this->findOneBy(['client' => $client, 'type' => $type, 'endedAt' => null]);
    }

    /**
     * @return list<ClientAssignmentLog> najnovije prvo
     */
    public function findByClient(Client $client): array
    {
        return $this->findBy(['client' => $client], ['startedAt' => 'DESC', 'id' => 'DESC']);
    }
}
