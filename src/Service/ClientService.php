<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientAssignmentLog;
use App\Entity\User;
use App\Enum\AssignmentType;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Repository\ClientAssignmentLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class ClientService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientAssignmentLogRepository $logs,
        private readonly UserRepository $users,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Admin+ bira među svim aktivnim radnicima, a šef samo među svojim timom.
     */
    public function canAssignAnyone(User $actor): bool
    {
        return in_array(Role::ADMIN->value, $this->roleHierarchy->getReachableRoleNames($actor->getRoles()), true);
    }

    public function create(Client $client, User $primary, ?User $backup, User $actor): void
    {
        $this->validateAssignees($client, $primary, $backup, $actor);

        $this->em->wrapInTransaction(function () use ($client, $primary, $backup, $actor): void {
            $client->setPrimaryAccountant($primary);
            $client->setBackupAccountant($backup);
            $this->em->persist($client);
            $this->em->persist(new ClientAssignmentLog($client, $primary, AssignmentType::PRIMARY, $actor));
            if (null !== $backup) {
                $this->em->persist(new ClientAssignmentLog($client, $backup, AssignmentType::BACKUP, $actor));
            }
        });

        $this->notifyAssigned($client, $primary, AssignmentType::PRIMARY, $actor);
        if (null !== $backup) {
            $this->notifyAssigned($client, $backup, AssignmentType::BACKUP, $actor);
        }
    }

    /**
     * Čuva izmene podataka klijenta i, ako su se dodele promenile, beleži ih u istoriji.
     */
    public function update(Client $client, User $primary, ?User $backup, User $actor): void
    {
        $this->assign($client, $primary, $backup, $actor);
    }

    public function assign(Client $client, User $primary, ?User $backup, User $actor): void
    {
        $this->validateAssignees($client, $primary, $backup, $actor);

        $newPrimary = $client->getPrimaryAccountant() !== $primary;
        $newBackup = null !== $backup && $client->getBackupAccountant() !== $backup;

        $this->em->wrapInTransaction(function () use ($client, $primary, $backup, $actor): void {
            if ($client->getPrimaryAccountant() !== $primary) {
                $this->logs->findCurrent($client, AssignmentType::PRIMARY)?->setEndedAt(new \DateTimeImmutable());
                $client->setPrimaryAccountant($primary);
                $this->em->persist(new ClientAssignmentLog($client, $primary, AssignmentType::PRIMARY, $actor));
            }

            if ($client->getBackupAccountant() !== $backup) {
                $this->logs->findCurrent($client, AssignmentType::BACKUP)?->setEndedAt(new \DateTimeImmutable());
                $client->setBackupAccountant($backup);
                if (null !== $backup) {
                    $this->em->persist(new ClientAssignmentLog($client, $backup, AssignmentType::BACKUP, $actor));
                }
            }
        });

        if ($newPrimary) {
            $this->notifyAssigned($client, $primary, AssignmentType::PRIMARY, $actor);
        }
        if ($newBackup) {
            $this->notifyAssigned($client, $backup, AssignmentType::BACKUP, $actor);
        }
    }

    public function setActive(Client $client, bool $active): void
    {
        if ($active) {
            foreach ([$client->getPrimaryAccountant(), $client->getBackupAccountant()] as $user) {
                if (null !== $user && !$user->isActive()) {
                    throw new \DomainException(sprintf('%s je deaktiviran. Prvo promeni dodelu klijenta.', $user->getFullName()));
                }
            }
        }

        $client->setIsActive($active);
        $this->em->flush();
    }

    private function notifyAssigned(Client $client, User $user, AssignmentType $type, User $actor): void
    {
        $this->notifications->notify(
            $user,
            NotificationType::CLIENT_ASSIGNED,
            'Dodeljen ti je klijent',
            sprintf('Dodeljen ti je klijent „%s“, uloga: %s.', $client->getName(), AssignmentType::PRIMARY === $type ? 'glavni zaduženi' : 'backup (zamena)'),
            ['client_show', ['id' => $client->getId()]],
            $actor,
        );
    }

    /**
     * Glavni i backup su različiti i aktivni. Šef sme da postavi samo nove radnike iz svog opsega,
     * a već zadužene (i neaktivne) ne dira ako se nisu menjali.
     */
    private function validateAssignees(Client $client, User $primary, ?User $backup, User $actor): void
    {
        if (null !== $backup && $backup === $primary) {
            throw new \DomainException('Glavni zaduženi i backup moraju biti različite osobe.');
        }

        $changes = [];
        if ($client->getPrimaryAccountant() !== $primary) {
            $changes[] = $primary;
        }
        if (null !== $backup && $client->getBackupAccountant() !== $backup) {
            $changes[] = $backup;
        }

        foreach ($changes as $user) {
            if (!$user->isActive()) {
                throw new \DomainException(sprintf('%s je deaktiviran i ne može biti zadužen.', $user->getFullName()));
            }
            if (!$this->canAssignAnyone($actor) && !$this->users->isInLeaderScope($actor, $user)) {
                throw new \DomainException(sprintf('%s nije član tvog tima.', $user->getFullName()));
            }
        }
    }
}
