<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Enum\AbsenceStatus;
use App\Enum\NotificationType;
use App\Repository\ClientRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Odsustva i zamena (SPEC 5).
 * Radnik koji sam sebi upiše odsustvo čeka odobrenje šefa tima (ili admina, ako nema šefa).
 * Šef i admin upisuju odmah odobreno, pa i šef sebi. Backup preuzima klijente samo za ODOBRENO odsustvo.
 */
class AbsenceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserAbsenceRepository $absences,
        private readonly ClientRepository $clients,
        private readonly UserRepository $users,
        private readonly TeamMemberRepository $members,
        private readonly TaskService $tasks,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Ko za koga sme da upiše odsustvo: radnik za sebe, šef za sebe i svoj tim, admin+ za bilo koga.
     */
    public function canEnterFor(User $actor, User $user): bool
    {
        if ($this->tasks->isAdmin($actor)) {
            return true;
        }

        return $this->tasks->isLeader($actor) ? $this->users->isInLeaderScope($actor, $user) : $actor === $user;
    }

    /**
     * Ko odobrava: admin+, a šef samo za članove svog tima (nikad za sebe).
     */
    public function canDecide(User $actor, User $user): bool
    {
        if ($this->tasks->isAdmin($actor)) {
            return true;
        }

        return $actor !== $user && $this->tasks->isLeader($actor) && $this->users->isInLeaderScope($actor, $user);
    }

    /**
     * Upis odsustva. Šef i admin odobravaju odmah, radnik čeka odobrenje.
     */
    public function create(UserAbsence $absence, User $actor): void
    {
        $user = $absence->getUser() ?? throw new \DomainException('Izaberi radnika.');
        if (!$this->canEnterFor($actor, $user)) {
            throw new \DomainException('Ne možeš upisati odsustvo za ovog radnika.');
        }
        if (!$user->isActive()) {
            throw new \DomainException('Radnik nije aktivan.');
        }
        if ($this->absences->hasOverlap($user, $absence->getStartDate(), $absence->getEndDate())) {
            throw new \DomainException('Radnik već ima odsustvo koje se preklapa sa ovim periodom.');
        }

        $absence->setCreatedBy($actor);
        $immediate = $this->tasks->isAdmin($actor) || $this->tasks->isLeader($actor);

        if ($immediate) {
            $absence->setStatus(AbsenceStatus::ODOBRENO);
        } else {
            $absence->setStatus(AbsenceStatus::NA_CEKANJU);
        }
        $this->em->persist($absence);
        $this->em->flush();

        if ($immediate) {
            $this->activate($absence, $actor);
            if ($actor !== $user) {
                $this->notifications->notify(
                    $user,
                    NotificationType::ABSENCE_DECIDED,
                    'Upisano ti je odsustvo',
                    sprintf('%s ti je upisao odsustvo: %s.', $actor->getFullName(), $absence->summary()),
                    ['absence_index'],
                    $actor,
                );
            }
        } else {
            $this->notifications->notifyMany(
                $this->approversFor($user),
                NotificationType::ABSENCE_REQUESTED,
                'Odsustvo čeka odobrenje',
                sprintf('%s traži odsustvo: %s.', $user->getFullName(), $absence->summary()),
                ['absence_index'],
                $actor,
            );
        }
    }

    public function approve(UserAbsence $absence, User $actor): void
    {
        $this->assertPending($absence);
        $absence->decide(AbsenceStatus::ODOBRENO, $actor);
        $this->em->flush();

        $this->activate($absence, $actor);
        $this->notifications->notify(
            $absence->getUser(),
            NotificationType::ABSENCE_DECIDED,
            'Odsustvo je odobreno',
            sprintf('Tvoje odsustvo je odobreno: %s.', $absence->summary()),
            ['absence_index'],
            $actor,
        );
    }

    public function reject(UserAbsence $absence, User $actor): void
    {
        $this->assertPending($absence);
        $absence->decide(AbsenceStatus::ODBIJENO, $actor);
        $this->em->flush();

        $this->notifications->notify(
            $absence->getUser(),
            NotificationType::ABSENCE_DECIDED,
            'Odsustvo je odbijeno',
            sprintf('Tvoje odsustvo je odbijeno: %s.', $absence->summary()),
            ['absence_index'],
            $actor,
        );
    }

    /**
     * Otkazivanje odsustva koje čeka ili je odobreno. Zamena se tada završava.
     */
    public function cancel(UserAbsence $absence, User $actor): void
    {
        if (!in_array($absence->getStatus(), [AbsenceStatus::NA_CEKANJU, AbsenceStatus::ODOBRENO], true) || $absence->hasEnded()) {
            throw new \DomainException('Ovo odsustvo se više ne može otkazati.');
        }

        $wasApproved = AbsenceStatus::ODOBRENO === $absence->getStatus();
        $absence->setStatus(AbsenceStatus::OTKAZANO);
        $this->em->flush();

        if ($wasApproved) {
            foreach ($this->backupsFor($absence->getUser()) as $backup) {
                $this->notifications->notify(
                    $backup,
                    NotificationType::BACKUP_ACTIVATED,
                    'Zamena je otkazana',
                    sprintf('%s je otkazao odsustvo (%s). Više ne preuzimaš njegove klijente.', $absence->getUser()->getFullName(), $absence->summary()),
                    ['client_mine'],
                    $actor,
                );
            }
        }
    }

    /**
     * Klijenti koje backup trenutno preuzima, sa odsustvom glavnog zaduženog.
     *
     * @return list<array{client: Client, absence: UserAbsence}>
     */
    public function activeReplacements(User $backup, ?\DateTimeImmutable $date = null): array
    {
        $clients = $this->clients->findActiveBackupFor($backup);
        $primaries = [];
        foreach ($clients as $client) {
            $primaries[$client->getPrimaryAccountant()->getId()] = $client->getPrimaryAccountant();
        }
        $absent = $this->absences->findApprovedCoveringForUsers(array_values($primaries), $date ?? new \DateTimeImmutable('today'));

        $result = [];
        foreach ($clients as $client) {
            $absence = $absent[$client->getPrimaryAccountant()->getId()] ?? null;
            if (null !== $absence) {
                $result[] = ['client' => $client, 'absence' => $absence];
            }
        }

        return $result;
    }

    /**
     * Posle odobrenja: backup-ovi dobijaju obaveštenje "Preuzimaš klijente za ...".
     */
    private function activate(UserAbsence $absence, User $actor): void
    {
        $user = $absence->getUser();
        $byBackup = [];
        foreach ($this->clients->findActivePrimaryFor($user) as $client) {
            $backup = $client->getBackupAccountant();
            if (null !== $backup) {
                $byBackup[$backup->getId()]['user'] = $backup;
                $byBackup[$backup->getId()]['clients'][] = $client->getName();
            }
        }

        foreach ($byBackup as $row) {
            $this->notifications->notify(
                $row['user'],
                NotificationType::BACKUP_ACTIVATED,
                'Preuzimaš klijente kolege',
                sprintf(
                    'Preuzimaš klijente za %s od %s do %s: %s.',
                    $user->getFullName(),
                    $absence->getStartDate()->format('d.m.Y.'),
                    $absence->getEndDate()->format('d.m.Y.'),
                    implode(', ', $row['clients']),
                ),
                ['client_mine'],
                $actor,
            );
        }
    }

    /**
     * @return list<User> backup-ovi aktivnih klijenata radnika
     */
    private function backupsFor(User $user): array
    {
        $backups = [];
        foreach ($this->clients->findActivePrimaryFor($user) as $client) {
            if (null !== $client->getBackupAccountant()) {
                $backups[$client->getBackupAccountant()->getId()] = $client->getBackupAccountant();
            }
        }

        return array_values($backups);
    }

    /**
     * Ko odobrava odsustvo radnika: šef njegovog tima (ako nije sam radnik), a inače admini.
     *
     * @return list<User>
     */
    private function approversFor(User $user): array
    {
        $team = $this->teamLeader($user);

        return null !== $team && $team !== $user && $team->isActive() ? [$team] : $this->users->findActiveAdmins();
    }

    private function teamLeader(User $user): ?User
    {
        $team = $this->members->findActiveByUser($user)?->getTeam();

        return null !== $team && !$team->isDeleted() ? $team->getLeader() : null;
    }

    private function assertPending(UserAbsence $absence): void
    {
        if (AbsenceStatus::NA_CEKANJU !== $absence->getStatus()) {
            throw new \DomainException('Odsustvo nije na čekanju.');
        }
        if ($absence->hasEnded()) {
            throw new \DomainException('Period ovog odsustva je već prošao.');
        }
    }
}
