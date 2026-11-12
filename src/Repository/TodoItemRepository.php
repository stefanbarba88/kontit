<?php

namespace App\Repository;

use App\Entity\TodoItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * PRIVATNOST: svaki upit ovde je ograničen na vlasnika. Nema metode koja vraća tuđe stavke, pa ih ne mogu videti ni admin ni superadmin.
 * Sva vremena u parametrima su UTC (TodoClock); nikad NOW() iz baze.
 *
 * @extends ServiceEntityRepository<TodoItem>
 */
class TodoItemRepository extends ServiceEntityRepository
{
    public const DONE_LIMIT = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TodoItem::class);
    }

    /** Stavka samo ako pripada $owner; inače null (kontroler vraća 404, nikad 403) */
    public function findOwned(int $id, User $owner): ?TodoItem
    {
        return $this->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /**
     * Sve nezavršene stavke vlasnika (lična lista je mala; sekcije se razvrstavaju u PHP-u po pravilima Europe/Belgrade).
     *
     * @return list<TodoItem>
     */
    public function findOpen(User $owner): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :owner AND t.isDone = false')->setParameter('owner', $owner)
            ->orderBy('t.position', 'ASC')->addOrderBy('t.id', 'ASC')
            ->setMaxResults(1000)
            ->getQuery()->getResult();
    }

    /** @return list<TodoItem> poslednjih završenih */
    public function findRecentDone(User $owner, int $limit = self::DONE_LIMIT): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :owner AND t.isDone = true')->setParameter('owner', $owner)
            ->orderBy('t.doneAt', 'DESC')->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** Broj nezavršenih sa rokom pre $before (UTC): zakasnelo + danas kad je $before početak sutrašnjeg dana */
    public function countDueBefore(User $owner, \DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('t')->select('COUNT(t.id)')
            ->andWhere('t.owner = :owner AND t.isDone = false AND t.dueAt IS NOT NULL AND t.dueAt < :before')
            ->setParameter('owner', $owner)->setParameter('before', $before)
            ->getQuery()->getSingleScalarResult();
    }

    /** Pozicija za novu stavku (na kraj "Bez roka") */
    public function nextPosition(User $owner): int
    {
        return 1 + (int) $this->createQueryBuilder('t')->select('COALESCE(MAX(t.position), 0)')
            ->andWhere('t.owner = :owner')->setParameter('owner', $owner)
            ->getQuery()->getSingleScalarResult();
    }

    /** Stavka nastala završetkom $source (ponavljanje) */
    public function findRepeatChild(TodoItem $source): ?TodoItem
    {
        return $this->findOneBy(['repeatSource' => $source, 'owner' => $source->getOwner()]);
    }

    /**
     * Pretraga po naslovu i napomeni, samo među stavkama vlasnika.
     *
     * @return list<TodoItem>
     */
    public function search(User $owner, string $query, int $limit = 100): array
    {
        $like = '%'.addcslashes(trim($query), '%_\\').'%';

        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :owner AND (t.title LIKE :q OR t.note LIKE :q)')
            ->setParameter('owner', $owner)->setParameter('q', $like)
            ->orderBy('t.isDone', 'ASC')->addOrderBy('t.dueAt', 'ASC')->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
