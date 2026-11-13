<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function createListQueryBuilder(User $user, bool $onlyUnread = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC');

        if ($onlyUnread) {
            $qb->andWhere('n.isRead = false');
        }

        return $qb;
    }

    /**
     * @return list<Notification>
     */
    public function findLatest(User $user, int $limit = 10): array
    {
        return $this->createListQueryBuilder($user)->setMaxResults($limit)->getQuery()->getResult();
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user AND n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllRead(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->andWhere('n.user = :user AND n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Da li korisnik već ima notifikaciju ovog tipa za isti link (da se rokovi ne ponavljaju).
     */
    /** Obaveštenje iste vrste za isti link već postoji od zadatog trenutka (npr. od početka dana, za dnevno upozorenje) */
    public function existsSince(User $user, NotificationType $type, string $link, \DateTimeImmutable $since): bool
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user AND n.type = :type AND n.link = :link AND n.createdAt >= :since')
            ->setParameter('user', $user)->setParameter('type', $type)->setParameter('link', $link)->setParameter('since', $since)
            ->getQuery()->getSingleScalarResult() > 0;
    }

    public function exists(User $user, NotificationType $type, string $link): bool
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user AND n.type = :type AND n.link = :link')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('link', $link)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
