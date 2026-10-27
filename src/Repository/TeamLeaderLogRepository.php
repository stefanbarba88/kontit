<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamLeaderLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamLeaderLog>
 */
class TeamLeaderLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamLeaderLog::class);
    }

    public function findCurrent(Team $team): ?TeamLeaderLog
    {
        return $this->findOneBy(['team' => $team, 'endedAt' => null]);
    }

    /**
     * @return list<TeamLeaderLog>
     */
    public function findByTeam(Team $team): array
    {
        return $this->findBy(['team' => $team], ['startedAt' => 'DESC', 'id' => 'DESC']);
    }
}
