<?php

namespace App\Repository;

use App\Entity\ClientPackagePriceLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientPackagePriceLog>
 */
class ClientPackagePriceLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientPackagePriceLog::class);
    }
}
