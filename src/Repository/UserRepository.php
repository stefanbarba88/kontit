<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\Role;
use App\Security\TeamScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function createListQueryBuilder(?string $search, ?string $role, ?string $status, ?int $positionId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')->addSelect('p')->leftJoin('u.position', 'p')->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC');

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('u.firstName LIKE :q OR u.lastName LIKE :q OR u.email LIKE :q OR CONCAT(u.firstName, \' \', u.lastName) LIKE :q')
                ->setParameter('q', '%'.trim($search).'%');
        }

        if (null !== $role && null !== Role::tryFrom($role)) {
            // roles je JSON niz, čuva se jedna rola po korisniku
            $qb->andWhere(Role::RADNIK->value === $role ? 'u.roles LIKE :role OR u.roles = \'[]\'' : 'u.roles LIKE :role')
                ->setParameter('role', '%"'.$role.'"%');
        }

        if (null !== $positionId && $positionId > 0) {
            $qb->andWhere('u.position = :position')->setParameter('position', $positionId);
        }

        if ('active' === $status) {
            $qb->andWhere('u.isActive = true');
        } elseif ('inactive' === $status) {
            $qb->andWhere('u.isActive = false');
        }

        return $qb;
    }

    /**
     * Aktivni korisnici za izbor u formama (šef tima, novi član).
     *
     * @return QueryBuilder
     */
    public function createActiveChoiceQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC');
    }

    /**
     * Izbor šefa tima: samo aktivni korisnici sa rolom SEF. Trenutni šef ostaje u izboru
     * (npr. ako mu je rola kasnije promenjena), da forma na izmeni ostane validna.
     */
    public function createLeaderChoiceQueryBuilder(?User $current = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC');

        $cond = 'u.roles LIKE :sef';
        $qb->setParameter('sef', '%"'.Role::SEF->value.'"%');
        if (null !== $current) {
            $cond .= ' OR u = :current';
            $qb->setParameter('current', $current);
        }

        return $qb->andWhere($cond);
    }

    /**
     * Aktivni admini i superadmini (primaoci obaveštenja kad zadatak nema šefa koji ga može potvrditi).
     *
     * @return list<User>
     */
    public function findActiveAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->andWhere('u.roles LIKE :admin OR u.roles LIKE :superadmin')
            ->setParameter('admin', '%"'.Role::ADMIN->value.'"%')
            ->setParameter('superadmin', '%"'.Role::SUPERADMIN->value.'"%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Radnici koje akter sme da izabere kao glavnog/backup na klijentu.
     * Admin+ ($all): svi aktivni. Šef: aktivni članovi timova koje vodi i on sam.
     * Trenutno zadužene ($current) uvek ostaju u izboru, da forma na izmeni ostane validna.
     *
     * @param list<User> $current
     */
    public function createAssignableQueryBuilder(User $actor, bool $all, array $current = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC');

        $scope = $qb->expr()->andX('u.isActive = true');
        if (!$all) {
            $scope = $qb->expr()->andX(
                'u.isActive = true',
                'u = :actor OR (EXISTS ('
                .'SELECT 1 FROM App\Entity\TeamMember m JOIN m.team t '
                .'WHERE m.user = u AND m.leftAt IS NULL AND t.deletedAt IS NULL AND t.leader = :actor) AND '.TeamScope::worker('u').')',
            );
            $qb->setParameter('actor', $actor);
        }

        if ([] !== $current) {
            $qb->andWhere($qb->expr()->orX($scope, 'u IN (:current)'))->setParameter('current', $current);
        } else {
            $qb->andWhere($scope);
        }

        return $qb;
    }

    /**
     * Da li je $user u opsegu šefa: on sam ili aktivan član tima koji $leader vodi.
     */
    public function isInLeaderScope(User $leader, User $user): bool
    {
        if ($leader === $user) {
            return true;
        }
        // šef ne vidi detalje člana tima koji je šef ili admin (opšte pravilo, TeamScope)
        if (!TeamScope::covers($leader, $user)) {
            return false;
        }

        return (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(m.id) FROM App\Entity\TeamMember m JOIN m.team t '
            .'WHERE m.user = :user AND m.leftAt IS NULL AND t.deletedAt IS NULL AND t.leader = :leader'
        )->setParameters(['user' => $user, 'leader' => $leader])->getSingleScalarResult() > 0;
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Mogući proveravači posebne potvrde: aktivni admini i superadmini, plus već izabrani (i ako su u međuvremenu deaktivirani).
     *
     * @param iterable<User> $alsoInclude
     */
    public function createReviewApproverQueryBuilder(iterable $alsoInclude = []): QueryBuilder
    {
        $keep = [];
        foreach ($alsoInclude as $user) {
            $keep[] = $user->getId();
        }
        $qb = $this->createQueryBuilder('u')
            ->andWhere('(u.isActive = true AND (u.roles LIKE :admin OR u.roles LIKE :superadmin)) OR u.id IN (:keep)')
            ->setParameter('admin', '%"'.Role::ADMIN->value.'"%')
            ->setParameter('superadmin', '%"'.Role::SUPERADMIN->value.'"%')
            ->setParameter('keep', [] === $keep ? [0] : $keep)
            ->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC');

        return $qb;
    }}
