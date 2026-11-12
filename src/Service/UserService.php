<?php

namespace App\Service;

use App\Entity\Position;
use App\Entity\User;
use App\Entity\UserPositionLog;
use App\Enum\Role;
use App\Repository\ClientRepository;
use App\Repository\TaskRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TeamRepository $teams,
        private readonly ClientRepository $clients,
        private readonly TaskRepository $tasks,
    ) {
    }

    /**
     * Role koje akter sme da dodeli. Admine i superadmine pravi samo superadmin.
     *
     * @return list<Role>
     */
    public function assignableRoles(User $actor): array
    {
        if (Role::SUPERADMIN === $actor->getHighestRole()) {
            return [Role::RADNIK, Role::SEF, Role::ADMIN, Role::SUPERADMIN];
        }

        return [Role::RADNIK, Role::SEF];
    }

    public function save(User $user, ?string $plainPassword, ?Role $role = null): void
    {
        if (null !== $role) {
            // Šef tima ne može da padne ispod ROLE_SEF dok vodi tim
            if (Role::RADNIK === $role && $user->getId() && $this->teams->countActiveLedBy($user) > 0) {
                throw new \DomainException('Korisnik vodi tim. Prvo promeni šefa tima.');
            }
            $user->setHighestRole($role);
        }

        if (null !== $plainPassword && '' !== $plainPassword) {
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        }

        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * Beleži promenu pozicije u istoriju napredovanja (UserPositionLog). Poziva se posle čuvanja korisnika, sa pozicijom od pre izmene.
     * Pozicija ne menja ROLE_*: prava pristupa se menjaju samo u save().
     */
    public function logPositionChange(User $user, ?Position $before, User $actor, ?string $note = null): void
    {
        if ($before === $user->getPosition()) {
            return;
        }
        $this->em->persist(new UserPositionLog($user, $before, $user->getPosition(), $actor, $note));
        $this->em->flush();
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->em->flush();
    }

    public function setActive(User $user, bool $active, User $actor): void
    {
        if (!$active) {
            if ($user === $actor) {
                throw new \DomainException('Ne možeš deaktivirati sopstveni nalog.');
            }
            if ($this->teams->countActiveLedBy($user) > 0) {
                throw new \DomainException('Korisnik vodi tim. Prvo promeni šefa tima.');
            }
            $clients = $this->clients->countActiveAssignedTo($user);
            if ($clients > 0) {
                throw new \DomainException(sprintf('Korisnik je zadužen na %d klijent(a) kao glavni ili backup. Prvo prebacite klijente na drugog radnika.', $clients));
            }
            $tasks = $this->tasks->countPendingFor($user);
            if ($tasks > 0) {
                throw new \DomainException(sprintf('Korisnik ima %d zadatak(a) u radu. Prvo ih prebacite na drugog radnika (izmena zadatka) ili ih otkažite.', $tasks));
            }
        }

        $user->setIsActive($active);
        $this->em->flush();
    }
}
