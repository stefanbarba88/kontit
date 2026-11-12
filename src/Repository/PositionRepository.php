<?php

namespace App\Repository;

use App\Entity\Position;
use App\Entity\User;
use App\Entity\UserPositionLog;
use App\Service\TextNormalizer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * Pozicije po senioritetu. Za forme: $activeOnly nudi samo aktivne, ali uvek i $alsoInclude (pozicija koju korisnik već ima).
     *
     * @return list<Position>
     */
    public function findOrdered(bool $activeOnly = false, ?Position $alsoInclude = null): array
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.level', 'ASC')->addOrderBy('p.name', 'ASC');
        if ($activeOnly) {
            if (null !== $alsoInclude && null !== $alsoInclude->getId()) {
                $qb->andWhere('p.isActive = true OR p = :keep')->setParameter('keep', $alsoInclude);
            } else {
                $qb->andWhere('p.isActive = true');
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Broj korisnika po poziciji (id => broj).
     *
     * @return array<int, int>
     */
    public function userCounts(): array
    {
        $rows = $this->getEntityManager()->createQuery('SELECT IDENTITY(u.position) AS id, COUNT(u.id) AS n FROM '.User::class.' u WHERE u.position IS NOT NULL GROUP BY u.position')->getArrayResult();

        return array_column($rows, 'n', 'id');
    }

    public function userCount(Position $position): int
    {
        return (int) $this->getEntityManager()->createQuery('SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.position = :p')->setParameter('p', $position)->getSingleScalarResult();
    }

    /** Pozicija je ikad korišćena: ima korisnike ili je u istoriji (kao from ili to). Takva se ne briše, samo deaktivira. */
    public function isUsed(Position $position): bool
    {
        if ($this->userCount($position) > 0) {
            return true;
        }

        return (int) $this->getEntityManager()->createQuery('SELECT COUNT(l.id) FROM '.UserPositionLog::class.' l WHERE l.fromPosition = :p OR l.toPosition = :p')
            ->setParameter('p', $position)->getSingleScalarResult() > 0;
    }

    public function findOneByNormalizedName(string $normalized, TextNormalizer $normalizer): ?Position
    {
        foreach ($this->findAll() as $position) {
            if ($normalizer->normalize($position->getName()) === $normalized) {
                return $position;
            }
        }

        return null;
    }
}
