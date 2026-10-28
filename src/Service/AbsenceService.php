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
 * Odsustva i zamena (SPEC 5, 8j).
 * Radnik podnosi zahtev za sebe; odobrava šef njegovog tima, a ako je podnosilac šef, nema tim ili mu je šef odsutan, admin.
 * Šef i admin koji upisuju odsustvo drugome upisuju ga odmah odobreno; admin i sebi. Vrsta bez odobrenja (bolovanje) je odmah na snazi.
 * Zahtev na odobrenju ima rok, posle koga se automatski odobrava (ApprovalService, SPEC 8m).
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
        private readonly WorkingDayService $workingDays,
        private readonly LeaveService $leave,
        private readonly ApprovalDeadlineCalculator $deadlines,
        private readonly SettingsService $settings,
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
     * Radni dani zahteva (bez vikenda, praznika i kolektivnog odmora); pola dana = 0,5.
     */
    public function workingDaysFor(\DateTimeImmutable $from, \DateTimeImmutable $to, bool $halfDay): float
    {
        $days = (float) $this->workingDays->countWorkingDays($from, $to);

        return $halfDay && $from == $to && $days > 0 ? 0.5 : $days;
    }

    /**
     * Podaci za formu zahteva (AJAX): radni dani, preostali godišnji posle zahteva, ko je još iz tima odsutan, upozorenja.
     *
     * @return array{workingDays: float, nonWorking: array<string, string>, leave: ?array{remaining: float, after: float}, teamAbsent: list<array{name: string, period: string, type: string, pending: bool}>, errors: list<string>, warnings: list<string>}
     */
    public function preview(User $user, ?\App\Entity\AbsenceType $type, \DateTimeImmutable $from, \DateTimeImmutable $to, bool $halfDay): array
    {
        $errors = [];
        $warnings = [];
        $days = $this->workingDaysFor($from, $to, $halfDay);
        if ($to < $from) {
            $errors[] = 'Kraj odsustva ne može biti pre početka.';
        } elseif (0.0 === $days) {
            $errors[] = 'U izabranom periodu nema radnih dana.';
        }
        if ($this->absences->hasOverlap($user, $from, $to)) {
            $errors[] = 'Već postoji odsustvo koje se preklapa sa ovim periodom.';
        }

        $leave = null;
        if (null !== $type && $type->isCountsAgainstLeave()) {
            $balance = $this->leave->balance($user, (int) $from->format('Y'));
            $leave = ['remaining' => $balance['remaining'] - $balance['pending'], 'after' => $balance['remaining'] - $balance['pending'] - $days];
            if ($leave['after'] < 0) {
                $warnings[] = sprintf('Posle ovog zahteva godišnji odmor je u minusu (%s dana).', number_format($leave['after'], 1, ',', ''));
            }
        }
        if (null !== $type && null !== $type->getMaxDaysPerYear()) {
            $usedOfType = $this->absences->sumTypeDays($user, $type, (int) $from->format('Y'));
            if ($usedOfType + $days > $type->getMaxDaysPerYear()) {
                $errors[] = sprintf('Za „%s“ je dozvoljeno najviše %d dana godišnje (već: %s).', $type->getName(), $type->getMaxDaysPerYear(), number_format($usedOfType, 1, ',', ''));
            }
        }

        // ko je još iz tima odsutan u tom periodu (upozorenje, ne zabrana)
        $teamAbsent = [];
        foreach ($this->absences->findInPeriod($this->teammates($user), $from, $to) as $other) {
            $teamAbsent[] = [
                'name' => $other->getUser()->getFullName(),
                'period' => $other->getStartDate()->format('d.m.').' – '.$other->getEndDate()->format('d.m.'),
                'type' => (string) $other->getType()?->getName(),
                'pending' => $other->isPending(),
            ];
        }

        return [
            'workingDays' => $days,
            'nonWorking' => $this->workingDays->nonWorkingDaysBetween($from, min($to, $from->modify('+62 days'))),
            'leave' => $leave,
            'teamAbsent' => $teamAbsent,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Upis odsustva (SPEC 8j.2).
     */
    public function create(UserAbsence $absence, User $actor): void
    {
        $user = $absence->getUser() ?? throw new \DomainException('Izaberi radnika.');
        $type = $absence->getType() ?? throw new \DomainException('Izaberi vrstu odsustva.');
        if (!$this->canEnterFor($actor, $user)) {
            throw new \DomainException('Ne možeš upisati odsustvo za ovog radnika.');
        }
        if (!$user->isActive()) {
            throw new \DomainException('Radnik nije aktivan.');
        }
        if (!$type->isActive()) {
            throw new \DomainException('Ova vrsta odsustva se više ne koristi.');
        }
        $preview = $this->preview($user, $type, $absence->getStartDate(), $absence->getEndDate(), $absence->isHalfDay());
        if ([] !== $preview['errors']) {
            throw new \DomainException($preview['errors'][0]);
        }
        $absence->setHalfDay($absence->isHalfDay() && $absence->getStartDate() == $absence->getEndDate())
            ->setWorkingDays($preview['workingDays'])
            ->setCreatedBy($actor);

        // odmah na snazi: vrsta bez odobrenja (evidencija), šef/admin upisuje drugome, admin sebi
        $immediate = !$type->isRequiresApproval()
            || ($actor !== $user && ($this->tasks->isAdmin($actor) || $this->tasks->isLeader($actor)))
            || $this->tasks->isAdmin($actor);

        if ($immediate) {
            $absence->setStatus(AbsenceStatus::ODOBRENO);
            if ($type->isRequiresApproval()) {
                $absence->decide(AbsenceStatus::ODOBRENO, $actor);
            }
        } else {
            $now = new \DateTimeImmutable();
            $hours = (int) $this->settings->getFloat(SettingsService::ABSENCE_APPROVAL_HOURS);
            $absence->setStatus(AbsenceStatus::NA_ODOBRENJU)
                ->setApprovalDeadline($this->deadlines->deadline($now, $hours, $absence->getStartDate()));
        }
        $this->em->persist($absence);
        $this->em->flush();

        if ($immediate) {
            $this->activate($absence, $actor);
            if ($actor !== $user) {
                $this->notifications->notify($user, NotificationType::ABSENCE_APPROVED, 'Upisano ti je odsustvo', sprintf('%s ti je upisao odsustvo: %s.', $actor->getFullName(), $absence->summary()), ['absence_index'], $actor);
            }
        } else {
            $urgent = $this->deadlines->isUrgent(new \DateTimeImmutable(), $absence->getApprovalDeadline(), (int) $this->settings->getFloat(SettingsService::ABSENCE_APPROVAL_HOURS));
            $this->notifications->notifyMany(
                $this->approversFor($user),
                NotificationType::ABSENCE_REQUESTED,
                $urgent ? 'HITNO: odsustvo čeka odobrenje' : 'Odsustvo čeka odobrenje',
                sprintf('%s traži odsustvo: %s. Ako ne odgovoriš do %s, zahtev se automatski odobrava.', $user->getFullName(), $absence->summary(), $absence->getApprovalDeadline()->format('d.m.Y. H:i')),
                ['absence_index', ['tab' => 'odobrenje']],
                $actor,
                $urgent,
            );
        }
    }

    public function approve(UserAbsence $absence, User $actor): void
    {
        $this->assertPending($absence);
        $absence->approve($actor);
        $this->em->flush();

        $this->activate($absence, $actor);
        $this->notifications->notify($absence->getUser(), NotificationType::ABSENCE_APPROVED, 'Odsustvo je odobreno', sprintf('Tvoje odsustvo je odobreno: %s.', $absence->summary()), ['absence_index'], $actor);
    }

    public function reject(UserAbsence $absence, User $actor, string $reason): void
    {
        $this->assertPending($absence);
        if ('' === trim($reason)) {
            throw new \DomainException('Unesi razlog odbijanja.');
        }
        $absence->decide(AbsenceStatus::ODBIJENO, $actor, $reason);
        $this->em->flush();

        $this->notifications->notify($absence->getUser(), NotificationType::ABSENCE_REJECTED, 'Odsustvo je odbijeno', sprintf('Tvoje odsustvo je odbijeno: %s. Razlog: %s', $absence->summary(), trim($reason)), ['absence_index'], $actor);
    }

    /**
     * Otkazivanje: podnosilac dok je zahtev na odobrenju, admin u bilo kom trenutku. Zamena se tada završava.
     */
    public function cancel(UserAbsence $absence, User $actor): void
    {
        if (!\in_array($absence->getStatus(), AbsenceStatus::blocking(), true)) {
            throw new \DomainException('Ovo odsustvo se više ne može otkazati.');
        }

        $wasApproved = $absence->isApproved();
        $absence->setStatus(AbsenceStatus::OTKAZANO);
        $this->em->flush();

        if ($wasApproved && !$absence->hasEnded()) {
            foreach ($this->backupsFor($absence->getUser()) as $backup) {
                $this->notifications->notify($backup, NotificationType::BACKUP_ACTIVATED, 'Zamena je otkazana', sprintf('%s je otkazao odsustvo (%s). Više ne preuzimaš njegove klijente.', $absence->getUser()->getFullName(), $absence->summary()), ['client_mine'], $actor);
            }
        }
        if ($actor !== $absence->getUser()) {
            $this->notifications->notify($absence->getUser(), NotificationType::ABSENCE_REJECTED, 'Odsustvo je otkazano', sprintf('%s je otkazao tvoje odsustvo: %s.', $actor->getFullName(), $absence->summary()), ['absence_index'], $actor);
        }
    }

    /**
     * Admin poništava automatsko odobrenje (SPEC 8m): obavezan razlog, zahtev postaje odbijen, zamena se završava.
     */
    public function revokeAutoApproval(UserAbsence $absence, User $actor, string $reason): void
    {
        if (AbsenceStatus::AUTOMATSKI_ODOBRENO !== $absence->getStatus()) {
            throw new \DomainException('Poništiti se može samo automatsko odobrenje.');
        }
        if ('' === trim($reason)) {
            throw new \DomainException('Unesi razlog poništavanja.');
        }
        $absence->decide(AbsenceStatus::ODBIJENO, $actor, $reason);
        $this->em->flush();

        $this->notifications->notify($absence->getUser(), NotificationType::ABSENCE_REJECTED, 'Automatsko odobrenje je poništeno', sprintf('%s je poništio automatsko odobrenje tvog odsustva (%s). Razlog: %s', $actor->getFullName(), $absence->summary(), trim($reason)), ['absence_index'], $actor);
        if (!$absence->hasEnded()) {
            foreach ($this->backupsFor($absence->getUser()) as $backup) {
                $this->notifications->notify($backup, NotificationType::BACKUP_ACTIVATED, 'Zamena je otkazana', sprintf('Odsustvo za %s (%s) više ne važi. Ne preuzimaš njegove klijente.', $absence->getUser()->getFullName(), $absence->summary()), ['client_mine'], $actor);
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
     * Ko odobrava odsustvo radnika: šef njegovog tima, ako to nije sam radnik i ako šef danas nije odsutan; inače admini.
     *
     * @return list<User>
     */
    public function approversFor(User $user): array
    {
        $leader = $this->teamLeader($user);
        if (null !== $leader && $leader !== $user && $leader->isActive()
            && null === $this->absences->findApprovedCovering($leader, new \DateTimeImmutable('today'))) {
            return [$leader];
        }

        return $this->users->findActiveAdmins();
    }

    /**
     * Posle odobrenja: backup-ovi dobijaju obaveštenje "Preuzimaš klijente za ...".
     */
    public function activate(UserAbsence $absence, ?User $actor): void
    {
        $user = $absence->getUser();
        $byBackup = [];
        foreach ($this->clients->findActivePrimaryFor($user) as $client) {
            $backup = $client->getBackupAccountant();
            if (null !== $backup) {
                $byBackup[$backup->getId()]['user'] = $backup;
                $byBackup[$backup->getId()]['clients'][] = $client->getShortName();
            }
        }

        foreach ($byBackup as $row) {
            $this->notifications->notify(
                $row['user'],
                NotificationType::BACKUP_ACTIVATED,
                'Preuzimaš klijente kolege',
                sprintf('Preuzimaš klijente za %s od %s do %s: %s.', $user->getFullName(), $absence->getStartDate()->format('d.m.Y.'), $absence->getEndDate()->format('d.m.Y.'), implode(', ', $row['clients'])),
                ['client_mine'],
                $actor,
            );
        }
    }

    /**
     * Aktivni članovi istih timova kao radnik (bez njega), za upozorenje "ko je još odsutan".
     *
     * @return list<User>
     */
    public function teammates(User $user): array
    {
        $team = $this->members->findActiveByUser($user)?->getTeam();
        if (null === $team) {
            return [];
        }
        $out = [];
        foreach ($this->members->findBy(['team' => $team, 'leftAt' => null]) as $member) {
            if ($member->getUser() !== $user && $member->getUser()->isActive()) {
                $out[] = $member->getUser();
            }
        }
        if (null !== ($leader = $team->getLeader()) && $leader !== $user && !\in_array($leader, $out, true)) {
            $out[] = $leader;
        }

        return $out;
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

    private function teamLeader(User $user): ?User
    {
        $team = $this->members->findActiveByUser($user)?->getTeam();

        return null !== $team && !$team->isDeleted() ? $team->getLeader() : null;
    }

    private function assertPending(UserAbsence $absence): void
    {
        if (!$absence->isPending()) {
            throw new \DomainException('Odsustvo nije na odobrenju.');
        }
    }
}
