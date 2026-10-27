<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ClientPackage;
use App\Entity\ServicePackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientPackage>
 */
class ClientPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientPackage::class);
    }

    /**
     * @return list<ClientPackage>
     */
    public function findActiveByPackage(ServicePackage $package): array
    {
        return $this->createQueryBuilder('cp')
            ->join('cp.client', 'c')
            ->andWhere('cp.package = :p')->andWhere('cp.isActive = true')->andWhere('c.isActive = true')
            ->setParameter('p', $package)
            ->getQuery()->getResult();
    }

    /**
     * @return list<ClientPackage>
     */
    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.client = :c')->setParameter('c', $client)
            ->orderBy('cp.isActive', 'DESC')->addOrderBy('cp.startDate', 'DESC')
            ->getQuery()->getResult();
    }
}
