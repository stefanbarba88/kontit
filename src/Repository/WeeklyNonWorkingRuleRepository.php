<?php

namespace App\Repository;

use App\Entity\WeeklyNonWorkingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeeklyNonWorkingRule>
 */
class WeeklyNonWorkingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeeklyNonWorkingRule::class);
    }

    /**
     * Jedino pravilo firme (pravi se ako ne postoji, ali se ne upisuje dok se ne sačuva).
     */
    public function get(): WeeklyNonWorkingRule
    {
        return $this->findOneBy([], ['id' => 'ASC']) ?? new WeeklyNonWorkingRule();
    }
}
