<?php

namespace App\Repository;

use App\Entity\AbsenceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AbsenceType>
 */
class AbsenceTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceType::class);
    }

    /**
     * @return list<AbsenceType>
     */
    public function findOrdered(bool $activeOnly = false): array
    {
        return $this->findBy($activeOnly ? ['isActive' => true] : [], ['position' => 'ASC', 'name' => 'ASC']);
    }
}
