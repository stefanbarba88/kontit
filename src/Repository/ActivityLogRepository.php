<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Strana timeline-a jednog entiteta, najnovije prvo (indeks entity_type, entity_id, created_at).
     *
     * @return array{entries: list<ActivityLog>, hasMore: bool}
     */
    public function findEntityPage(string $type, int $id, ActivityFilter $filter = new ActivityFilter()): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.entityType = :type AND l.entityId = :id')
            ->setParameter('type', $type)
            ->setParameter('id', $id);

        return $this->page($qb, $filter);
    }

    /**
     * Strana aktivnosti korisnika: ono što je on radio i ono što se dešavalo njemu (npr. promena tima).
     *
     * @return array{entries: list<ActivityLog>, hasMore: bool}
     */
    public function findUserPage(User $user, ActivityFilter $filter = new ActivityFilter()): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.user = :user OR (l.entityType = :userType AND l.entityId = :id)')
            ->setParameter('user', $user)
            ->setParameter('userType', ActivityLog::USER)
            ->setParameter('id', $user->getId());

        return $this->page($qb, $filter);
    }

    /**
     * LIMIT/OFFSET sa jednim redom viška, da se zna da li postoji sledeća strana.
     *
     * @return array{entries: list<ActivityLog>, hasMore: bool}
     */
    private function page(QueryBuilder $qb, ActivityFilter $filter): array
    {
        if (null !== $filter->action) {
            $qb->andWhere('l.action = :action')->setParameter('action', $filter->action);
        }
        if (null !== $filter->from) {
            $qb->andWhere('l.createdAt >= :from')->setParameter('from', $filter->from);
        }
        if (null !== $filter->to) {
            $qb->andWhere('l.createdAt < :to')->setParameter('to', $filter->to->modify('+1 day'));
        }

        $rows = $qb->addSelect('u')
            ->leftJoin('l.user', 'u')
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult($filter->offset())
            ->setMaxResults(ActivityFilter::PER_PAGE + 1)
            ->getQuery()
            ->getResult();

        return [
            'entries' => \array_slice($rows, 0, ActivityFilter::PER_PAGE),
            'hasMore' => \count($rows) > ActivityFilter::PER_PAGE,
        ];
    }
}
