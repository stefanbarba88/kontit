<?php

namespace App\Repository;

use App\Entity\LeaveBalance;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveBalance>
 */
class LeaveBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveBalance::class);
    }

    public function findFor(User $user, int $year): ?LeaveBalance
    {
        return $this->findOneBy(['user' => $user, 'year' => $year]);
    }
}
