<?php

namespace App\Service;

use App\Entity\Team;
use App\Entity\TeamLeaderLog;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Repository\TeamLeaderLogRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;

class TeamService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TeamRepository $teams,
        private readonly TeamMemberRepository $members,
        private readonly TeamLeaderLogRepository $leaderLogs,
        private readonly NotificationService $notifications,
        private readonly \App\Repository\ClientRepository $clients,
        private readonly \App\Repository\TaskRepository $taskRepo,
    ) {
    }

    public function create(Team $team, User $leader, User $actor): void
    {
        $this->assertCanLead($leader);

        $this->em->wrapInTransaction(function () use ($team, $leader, $actor): void {
            $team->setLeader($leader);
            $this->em->persist($team);
            $this->em->persist(new TeamLeaderLog($team, $leader, $actor));
            $this->grantLeaderRole($leader);
        });

        $this->notifyLeader($team, $leader, $actor, true);
    }

    /**
     * Čuva izmene tima (naziv, opis, boja) i, ako je izabran drugi šef, beleži promenu šefa.
     */
    public function update(Team $team, User $leader, User $actor): void
    {
        $this->changeLeader($team, $leader, $actor);
        $this->em->flush();
    }

    public function changeLeader(Team $team, User $newLeader, User $actor): void
    {
        $oldLeader = $team->getLeader();
        if ($oldLeader === $newLeader) {
            return;
        }

        $this->assertCanLead($newLeader);

        $this->em->wrapInTransaction(function () use ($team, $newLeader, $oldLeader, $actor): void {
            $now = new \DateTimeImmutable();
            $this->leaderLogs->findCurrent($team)?->setEndedAt($now);

            $team->setLeader($newLeader);
            $this->em->persist(new TeamLeaderLog($team, $newLeader, $actor));
            $this->grantLeaderRole($newLeader);
            $this->em->flush();

            if (null !== $oldLeader) {
                $this->revokeLeaderRoleIfUnused($oldLeader);
            }
        });

        $this->notifyLeader($team, $newLeader, $actor, true);
        if (null !== $oldLeader) {
            $this->notifyLeader($team, $oldLeader, $actor, false);
        }
    }

    /**
     * Upozorenja za potvrdu premeštanja: radnici koji su već u nekom timu, sa brojem klijenata i otvorenih zadataka koji idu sa njima.
     *
     * @return array<int, string> [userId => tekst]
     */
    public function moveWarnings(): array
    {
        $warnings = [];
        foreach ($this->members->findAllActive() as $member) {
            $user = $member->getUser();
            $warnings[$user->getId()] = sprintf(
                '%s je sada u timu „%s“. Premestićete ga, a njegovi klijenti (%d) i otvoreni zadaci (%d) idu sa njim.',
                $user->getFullName(),
                $member->getTeam()->getName(),
                $this->clients->countActiveAssignedTo($user),
                $this->taskRepo->countPendingFor($user),
            );
        }

        return $warnings;
    }

    /**
     * Dodaje radnika u tim. Ako je već u drugom timu, premešta ga (stari red dobija leftAt).
     * Klijenti i zadaci su vezani za osobu, pa idu sa njim.
     *
     * @return Team|null tim iz kog je radnik premešten
     */
    public function addMember(Team $team, User $user, ?User $actor = null): ?Team
    {
        if ($team->isDeleted()) {
            throw new \DomainException('Tim je obrisan.');
        }
        if (!$user->isActive()) {
            throw new \DomainException('Korisnik je deaktiviran.');
        }

        $current = $this->members->findActiveByUser($user);
        if (null !== $current && $current->getTeam() === $team) {
            throw new \DomainException('Radnik je već član ovog tima.');
        }

        $previous = $current?->getTeam();

        $this->em->wrapInTransaction(function () use ($team, $user, $current): void {
            $now = new \DateTimeImmutable();
            $current?->setLeftAt($now);
            $this->em->persist(new TeamMember($team, $user, $now));
        });

        $this->notifications->notify(
            $user,
            NotificationType::TEAM_CHANGED,
            'Promena tima',
            null === $previous
                ? sprintf('Dodati ste u tim „%s“.', $team->getName())
                : sprintf('Prebačeni ste iz tima „%s“ u tim „%s“.', $previous->getName(), $team->getName()),
            null,
            $actor,
        );

        return $previous;
    }

    public function removeMember(TeamMember $member): void
    {
        if (!$member->isActive()) {
            throw new \DomainException('Radnik više nije član tima.');
        }

        $member->setLeftAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function delete(Team $team): void
    {
        if ($team->getActiveMembers()->count() > 0) {
            throw new \DomainException('Tim ima aktivne članove. Prvo ih premestite ili uklonite.');
        }

        $this->em->wrapInTransaction(function () use ($team): void {
            $now = new \DateTimeImmutable();
            $team->setDeletedAt($now);
            $this->leaderLogs->findCurrent($team)?->setEndedAt($now);
            $this->em->flush();

            if (null !== $team->getLeader()) {
                $this->revokeLeaderRoleIfUnused($team->getLeader());
            }
        });
    }

    /**
     * Šef (novi ili bivši) tima dobija obaveštenje. Radnik ne može da otvori stranicu tima, pa link ima samo šef.
     */
    private function notifyLeader(Team $team, User $leader, User $actor, bool $appointed): void
    {
        $this->notifications->notify(
            $leader,
            NotificationType::TEAM_CHANGED,
            $appointed ? 'Postavljeni ste za šefa tima' : 'Više niste šef tima',
            sprintf($appointed ? 'Postavljeni ste za šefa tima „%s“.' : 'Više niste šef tima „%s“.', $team->getName()),
            $appointed ? ['team_show', ['id' => $team->getId()]] : null,
            $actor,
        );
    }

    private function assertCanLead(User $user): void
    {
        if (!$user->isActive()) {
            throw new \DomainException('Deaktiviran korisnik ne može biti šef tima.');
        }
    }

    /**
     * Šef tima mora imati ROLE_SEF. Admin već ima sve što i šef.
     */
    private function grantLeaderRole(User $user): void
    {
        if (Role::RADNIK === $user->getHighestRole()) {
            $user->setHighestRole(Role::SEF);
        }
    }

    /**
     * Kad korisnik prestane da vodi ijedan tim, vraća se na radnika (admin ostaje admin).
     */
    private function revokeLeaderRoleIfUnused(User $user): void
    {
        if (Role::SEF === $user->getHighestRole() && 0 === $this->teams->countActiveLedBy($user)) {
            $user->setHighestRole(Role::RADNIK);
            $this->em->flush();
        }
    }
}
