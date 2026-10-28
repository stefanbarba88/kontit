<?php

namespace App\Repository;

use App\Entity\ClientJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientJob>
 */
class ClientJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientJob::class);
    }

    /**
     * Aktivni, neisključeni i odobreni poslovi aktivnih klijenata (kandidati za generisanje zadataka).
     *
     * @return list<ClientJob>
     */
    public function findGeneratable(): array
    {
        return $this->createQueryBuilder('j')
            ->addSelect('t', 'c')
            ->join('j.jobTemplate', 't')
            ->join('j.client', 'c')
            ->andWhere('j.isActive = true')
            ->andWhere('j.isApproved = true')
            ->andWhere('j.excludedAt IS NULL')
            ->andWhere('t.isActive = true')
            ->andWhere('c.isActive = true')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ugovoreni poslovi (aktivni, odobreni, neisključeni) datih klijenata, za polje "Posao" u formi zadatka.
     * $current (posao već na zadatku) ostaje u izboru i kad više nije ugovoren.
     *
     * @param list<int> $clientIds
     */
    public function createContractedQueryBuilder(array $clientIds, ?ClientJob $current = null): QueryBuilder
    {
        return $this->createQueryBuilder('j')
            ->addSelect('t')
            ->join('j.jobTemplate', 't')
            ->join('t.category', 'cat')
            ->andWhere('(j.client IN (:clients) AND j.isActive = true AND j.isApproved = true AND j.isExcluded = false) OR j = :current')
            ->setParameter('clients', [] === $clientIds ? [0] : $clientIds)
            ->setParameter('current', $current?->getId() ?? 0)
            ->orderBy('cat.position', 'ASC')
            ->addOrderBy('t.name', 'ASC');
    }

    /**
     * @return list<ClientJob>
     */
    public function findContracted(\App\Entity\Client $client): array
    {
        return $this->createContractedQueryBuilder([(int) $client->getId()])->getQuery()->getResult();
    }
}