<?php

namespace App\Repository;

use App\Entity\NonWorkingDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NonWorkingDay>
 */
class NonWorkingDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NonWorkingDay::class);
    }

    /**
     * Zapisi koji utiču na godinu: praznici koji se ponavljaju i oni čiji opseg seče godinu.
     *
     * @return list<NonWorkingDay>
     */
    public function findForYear(int $year): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recurring = true OR (n.dateFrom <= :end AND n.dateTo >= :start)')
            ->setParameter('start', new \DateTimeImmutable($year.'-01-01'))
            ->setParameter('end', new \DateTimeImmutable($year.'-12-31'))
            ->orderBy('n.dateFrom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
