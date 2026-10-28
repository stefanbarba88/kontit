<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDocument>
 */
class UserDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDocument::class);
    }

    /**
     * Dokumenti korisnika (ili korpa: obrisani), najnoviji prvo, opciono jedna kategorija.
     *
     * @return list<UserDocument>
     */
    public function findForUser(User $user, bool $trash = false, ?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect('c', 'u')
            ->join('d.category', 'c')
            ->join('d.uploadedBy', 'u')
            ->andWhere('d.user = :user')->setParameter('user', $user)
            ->andWhere($trash ? 'd.deletedAt IS NOT NULL' : 'd.deletedAt IS NULL')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC');
        if (null !== $categoryId) {
            $qb->andWhere('c.id = :cat')->setParameter('cat', $categoryId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Dokumenti koji ističu do $until (za obaveštenja 30 i 7 dana pre isteka).
     *
     * @return list<UserDocument>
     */
    public function findExpiringBefore(\DateTimeImmutable $until): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.deletedAt IS NULL AND d.validUntil IS NOT NULL AND d.validUntil >= :today AND d.validUntil <= :until')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('until', $until)
            ->getQuery()
            ->getResult();
    }

    public function countForAbsence(\App\Entity\UserAbsence $absence): int
    {
        return (int) $this->createQueryBuilder('d')->select('COUNT(d.id)')
            ->andWhere('d.absence = :a AND d.deletedAt IS NULL')->setParameter('a', $absence)
            ->getQuery()->getSingleScalarResult();
    }
}
