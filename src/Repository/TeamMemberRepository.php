<?php

namespace App\Repository;

use App\Entity\TeamMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMember>
 */
class TeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMember::class);
    }

    public function findActiveByUser(User $user): ?TeamMember
    {
        return $this->findOneBy(['user' => $user, 'leftAt' => null]);
    }

    /**
     * @return list<TeamMember> aktivna članstva u timovima koji nisu obrisani
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('u', 't')
            ->join('m.user', 'u')
            ->join('m.team', 't')
            ->andWhere('m.leftAt IS NULL AND t.deletedAt IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<TeamMember> Celokupna istorija članstva, najnovije prvo
     */
    public function findHistoryByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('t')
            ->join('m.team', 't')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
