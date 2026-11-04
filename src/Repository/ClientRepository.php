<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Lista klijenata koje korisnik sme da vidi.
     * $all = admin+ (svi). Inače: svoji (glavni/backup) i klijenti čiji su radnici članovi timova koje korisnik vodi.
     */
    public function createVisibleQueryBuilder(User $user, bool $all, ?string $search, ?string $status): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('p', 'b')
            ->join('c.primaryAccountant', 'p')
            ->leftJoin('c.backupAccountant', 'b')
            ->orderBy('c.shortName', 'ASC');

        if (!$all) {
            $qb->andWhere('c.primaryAccountant = :me OR c.backupAccountant = :me OR EXISTS ('
                .'SELECT 1 FROM App\Entity\TeamMember m JOIN m.team t JOIN m.user mu '
                .'WHERE m.leftAt IS NULL AND t.deletedAt IS NULL AND t.leader = :me '
                .'AND (m.user = c.primaryAccountant OR m.user = c.backupAccountant) '
                ."AND (mu.roles NOT LIKE '%ROLE_SEF%' AND mu.roles NOT LIKE '%ROLE_ADMIN%' AND mu.roles NOT LIKE '%ROLE_SUPERADMIN%'))")
                ->setParameter('me', $user);
        }

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('c.shortName LIKE :q OR c.name LIKE :q OR c.pib LIKE :q OR c.maticniBroj LIKE :q OR c.city LIKE :q')
                ->setParameter('q', '%'.trim($search).'%');
        }

        if ('inactive' === $status) {
            $qb->andWhere('c.isActive = false');
        } elseif ('all' !== $status) {
            $qb->andWhere('c.isActive = true');
        }

        return $qb;
    }

    /**
     * @return list<Client> aktivni klijenti gde je korisnik glavni zadužen
     */
    public function findActivePrimaryFor(User $user): array
    {
        return $this->findAssignedQueryBuilder($user, 'primaryAccountant')->getQuery()->getResult();
    }

    /**
     * @return list<Client> aktivni klijenti gde je korisnik backup
     */
    public function findActiveBackupFor(User $user): array
    {
        return $this->findAssignedQueryBuilder($user, 'backupAccountant')->getQuery()->getResult();
    }

    /**
     * Koliko aktivnih klijenata ima korisnika kao glavnog ili backup radnika.
     */
    public function countActiveAssignedTo(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isActive = true')
            ->andWhere('c.primaryAccountant = :u OR c.backupAccountant = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function findAssignedQueryBuilder(User $user, string $field): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p', 'b')
            ->join('c.primaryAccountant', 'p')
            ->leftJoin('c.backupAccountant', 'b')
            ->andWhere('c.isActive = true')
            ->andWhere('c.'.$field.' = :u')
            ->setParameter('u', $user)
            ->orderBy('c.shortName', 'ASC');
    }
}
