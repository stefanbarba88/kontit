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

    /**
     * ID-evi stavki čiji je podsetnik stigao (remindAt <= $now) i još nije poslat. $now je UTC iz TodoClock, nikad NOW() iz baze.
     * Ovo je samo spisak kandidata; slanje se tek posle uspešnog {@see claimReminder()}.
     *
     * @return list<int>
     */
    public function findDueReminderIds(\DateTimeImmutable $now, int $limit = 500): array
    {
        $rows = $this->createQueryBuilder('t')->select('t.id')
            ->andWhere('t.remindAt IS NOT NULL AND t.remindAt <= :now AND t.reminderSentAt IS NULL AND t.isDone = false')
            ->setParameter('now', $now)
            ->orderBy('t.remindAt', 'ASC')->setMaxResults($limit)
            ->getQuery()->getScalarResult();

        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /**
     * Atomski "rezerviše" slanje podsetnika: samo proces koji promeni red (reminder_sent_at je bio NULL) sme da pošalje.
     * Zato dupli ili preklopljeni pokretanja komande ne šalju dvaput. Vreme je UTC parametar, ne NOW().
     */
    public function claimReminder(int $id, \DateTimeImmutable $now): bool
    {
        $moment = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return 1 === $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE todo_item SET reminder_sent_at = :now WHERE id = :id AND reminder_sent_at IS NULL AND is_done = 0 AND remind_at IS NOT NULL AND remind_at <= :now',
            ['now' => $moment, 'id' => $id],
        );
    }

    /**
     * Stavke vlasnika čiji je podsetnik poslat (posle $since, da se posle dužeg odsustva ne nakupi gomila toast-ova), a još nije prikazan kao toast ni u jednom tabu.
     *
     * @return list<TodoItem>
     */
    public function findShowable(User $owner, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :owner AND t.reminderSentAt IS NOT NULL AND t.reminderSentAt >= :since AND t.shownAt IS NULL AND t.isDone = false')
            ->setParameter('owner', $owner)->setParameter('since', $since)->orderBy('t.remindAt', 'ASC')->setMaxResults(20)
            ->getQuery()->getResult();
    }

    /** Atomski označava podsetnik kao prikazan: true samo za tab koji ga je prvi preuzeo (toast dobija jedan tab) */
    public function claimShown(int $id, User $owner, \DateTimeImmutable $now): bool
    {
        return 1 === $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE todo_item SET shown_at = :now WHERE id = :id AND owner_id = :owner AND shown_at IS NULL AND reminder_sent_at IS NOT NULL AND is_done = 0',
            ['now' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'id' => $id, 'owner' => $owner->getId()],
        );
    }
    /**
     * Nezavršene stavke vlasnika sa rokom u [$fromUtc, $toUtc) za kalendar.
     *
     * @return list<TodoItem>
     */
    public function findDueBetween(User $owner, \DateTimeImmutable $fromUtc, \DateTimeImmutable $toUtc): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :owner AND t.isDone = false AND t.dueAt IS NOT NULL AND t.dueAt >= :from AND t.dueAt < :to')
            ->setParameter('owner', $owner)->setParameter('from', $fromUtc)->setParameter('to', $toUtc)
            ->orderBy('t.dueAt', 'ASC')->setMaxResults(500)
            ->getQuery()->getResult();
    }
}
