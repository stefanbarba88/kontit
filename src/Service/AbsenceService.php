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
     * $details = false (radnik): od kolega samo odobrena odsustva, bez vrste (vidi se samo dostupnost).
     *
     * @return array{workingDays: float, nonWorking: array<string, string>, leave: ?array{remaining: float, after: float}, teamAbsent: list<array{name: string, period: string, type: string, pending: bool}>, errors: list<string>, warnings: list<string>}
     */
    public function preview(User $user, ?\App\Entity\AbsenceType $type, \DateTimeImmutable $from, \DateTimeImmutable $to, bool $halfDay, bool $details = true, ?UserAbsence $ignore = null): array
    {
        $errors = [];
        $warnings = [];
        $days = $this->workingDaysFor($from, $to, $halfDay);
        if ($to < $from) {
            $errors[] = 'Kraj odsustva ne može biti pre početka.';
        } elseif (0.0 === $days) {
            $errors[] = 'U izabranom periodu nema radnih dana.';
        }
        if ($this->absences->hasOverlap($user, $from, $to, $ignore)) {
            $errors[] = 'Već postoji odsustvo koje se preklapa sa ovim periodom.';
        }

        $leave = null;
        if (null !== $type && $type->isCountsAgainstLeave()) {
            $balance = $this->leave->balance($user, (int) $from->format('Y'));
            // pri izmeni se dani samog odsustva ne računaju dva puta
            $own = null !== $ignore && $ignore->getType()?->isCountsAgainstLeave() && $ignore->getStartDate()->format('Y') === $from->format('Y')
                && \in_array($ignore->getStatus(), AbsenceStatus::blocking(), true) ? $ignore->getWorkingDays() : 0.0;
            $leave = ['remaining' => $balance['remaining'] - $balance['pending'] + $own, 'after' => $balance['remaining'] - $balance['pending'] + $own - $days];
            if ($leave['after'] < 0) {
                $warnings[] = sprintf('Posle ovog zahteva godišnji odmor je u minusu (%s dana).', number_format($leave['after'], 1, ',', ''));
            }
        }
        if (null !== $type && null !== $type->getMaxDaysPerYear()) {
            $usedOfType = $this->absences->sumTypeDays($user, $type, (int) $from->format('Y'), $ignore);
            if ($usedOfType + $days > $type->getMaxDaysPerYear()) {
                $errors[] = sprintf('Za „%s“ je dozvoljeno najviše %d dana godišnje (već: %s).', $type->getName(), $type->getMaxDaysPerYear(), number_format($usedOfType, 1, ',', ''));
            }
        }

        // ko je još iz tima odsutan u tom periodu (upozorenje, ne zabrana)
        $teamAbsent = [];
        foreach ($this->absences->findInPeriod($this->teammates($user), $from, $to, $details) as $other) {
            $teamAbsent[] = [
                'name' => $other->getUser()->getFullName(),
                'period' => $other->getStartDate()->format('d.m.').' – '.$other->getEndDate()->format('d.m.'),
                'type' => $details ? (string) $other->getType()?->getName() : '',
                'pending' => $details && $other->isPending(),
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
        $this->assertDatesAllowed($actor, $absence->getStartDate(), $absence->getEndDate());
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
                sprintf('%s traži odsustvo: %s. Ako ne odgovoriš do %s, zahtev se automatski odobrava.', $user->getFullName(), $absence->summary(), $absence->getApprovalDeadline()->format(TimeFormat::DATETIME)),
                ['absence_index', ['tab' => 'odobrenje']],
                $actor,
                $urgent,
            );
        }
    }

    /**
     * Ko sme da menja odsustvo (vrstu, datume, napomenu), samo dok nije odbijeno ni otkazano:
     * admin+ sva, i u prošlosti; šef odsustva članova svog tima dok nisu završena; radnik (i šef za sebe) svoje,
     * samo dok je na odobrenju i dok nije počelo.
     */
    public function canEdit(User $actor, UserAbsence $absence): bool
    {
        if (!\in_array($absence->getStatus(), AbsenceStatus::blocking(), true)) {
            return false;
        }
        if ($this->tasks->isAdmin($actor)) {
            return true;
        }
        $user = $absence->getUser();
        if ($user !== $actor) {
            return $this->tasks->isLeader($actor) && $this->users->isInLeaderScope($actor, $user) && !$absence->hasEnded();
        }

        return $absence->isPending() && $absence->getStartDate() >= new \DateTimeImmutable('today');
    }

    /**
     * Izmena odsustva (vrsta, datumi, pola dana, napomena). $original = stanje pre izmene (forma menja entitet).
     * Ko nije admin ne može da upiše dan koji je prošao: početak se može ostaviti kakav je bio (odsustvo koje traje),
     * ali novi početak i kraj ne mogu biti pre današnjeg dana. Status ostaje (izmena nije odluka), osim kad radnik
     * na zahtevu koji čeka izabere vrstu bez odobrenja: tada je odmah na snazi, kao pri upisu.
     *
     * @param array{type: ?\App\Entity\AbsenceType, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, halfDay: bool, note: ?string} $original
     */
    public function update(UserAbsence $absence, User $actor, array $original): void
    {
        $user = $absence->getUser();
        $type = $absence->getType() ?? throw new \DomainException('Izaberi vrstu odsustva.');
        if (!$type->isActive() && $type !== $original['type']) {
            throw new \DomainException('Ova vrsta odsustva se više ne koristi.');
        }
        $this->assertDatesAllowed($actor, $absence->getStartDate(), $absence->getEndDate(), $original['startDate']);

        $preview = $this->preview($user, $type, $absence->getStartDate(), $absence->getEndDate(), $absence->isHalfDay(), true, $absence);
        if ([] !== $preview['errors']) {
            throw new \DomainException($preview['errors'][0]);
        }

        $periodChanged = $absence->getStartDate() != $original['startDate'] || $absence->getEndDate() != $original['endDate'];
        $changed = $periodChanged || $type !== $original['type'] || $absence->isHalfDay() !== $original['halfDay'] || (string) $absence->getNote() !== (string) $original['note'];
        if (!$changed) {
            return;
        }

        $absence->setHalfDay($absence->isHalfDay() && $absence->getStartDate() == $absence->getEndDate())
            ->setWorkingDays($preview['workingDays'])
            ->markEdited($actor);

        $activated = false;
        if ($absence->isPending()) {
            if (!$type->isRequiresApproval()) {
                $absence->setStatus(AbsenceStatus::ODOBRENO)->setApprovalDeadline(null);
                $activated = true;
            } elseif ($periodChanged) {
                // rok se računa od podnošenja, a ne od izmene (izmena ne produžava rok)
                $hours = (int) $this->settings->getFloat(SettingsService::ABSENCE_APPROVAL_HOURS);
                $absence->setApprovalDeadline($this->deadlines->deadline($absence->getCreatedAt() ?? new \DateTimeImmutable(), $hours, $absence->getStartDate()));
            }
        }
        $this->em->flush();

        // zamena dobija nov period
        if ($activated || ($absence->isApproved() && $periodChanged && !$absence->hasEnded())) {
            $this->activate($absence, $actor);
        }
        if ($actor !== $user) {
            $this->notifications->notify($user, NotificationType::ABSENCE_UPDATED, 'Izmenjeno ti je odsustvo', sprintf('%s je izmenio tvoje odsustvo: %s.', $actor->getFullName(), $absence->summary()), ['absence_index'], $actor);
        }
    }

    /**
     * Ko nije admin ne može da upiše odsustvo za dan koji je prošao (najraniji je danas). Admin sme i u prošlosti
     * (npr. naknadno upisano bolovanje). $originalStart: pri izmeni odsustva koje već traje početak može da ostane isti.
     */
    public function assertDatesAllowed(User $actor, \DateTimeImmutable $start, \DateTimeImmutable $end, ?\DateTimeImmutable $originalStart = null): void
    {
        if ($end < $start) {
            throw new \DomainException('Kraj odsustva ne može biti pre početka.');
        }
        if ($this->tasks->isAdmin($actor)) {
            return;
        }
        $today = new \DateTimeImmutable('today');
        if ($end < $today || ($start < $today && $start != $originalStart)) {
            throw new \DomainException('Ne možeš upisati odsustvo za dan koji je prošao. Najraniji datum je danas.');
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
                $this->notifications->notify($backup, NotificationType::BACKUP_ACTIVATED, 'Zamena je otkazana', sprintf('%s je otkazao odsustvo (%s). Više ne preuzimaš njegove klijente.', $absence->getUser()->getFullName(), $absence->periodText()), ['client_mine'], $actor);
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
                $this->notifications->notify($backup, NotificationType::BACKUP_ACTIVATED, 'Zamena je otkazana', sprintf('Odsustvo za %s (%s) više ne važi. Ne preuzimaš njegove klijente.', $absence->getUser()->getFullName(), $absence->periodText()), ['client_mine'], $actor);
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
     * Dostupnost kolega iz tima (stavka "odsustvo kolega"): samo odobrena odsustva, bez vrste i napomene.
     * Radnik vidi da li je kolega danas dostupan i kada nije u narednih $days dana.
     *
     * @return list<array{user: User, available: bool, periods: list<array{from: \DateTimeImmutable, to: \DateTimeImmutable}>}>
     */
    public function teamAvailability(User $me, int $days = 30): array
    {
        $today = new \DateTimeImmutable('today');
        $mates = $this->teammates($me);
        $periods = [];
        foreach ($this->absences->findInPeriod($mates, $today, $today->modify('+'.$days.' days'), false) as $absence) {
            $periods[$absence->getUser()->getId()][] = ['from' => $absence->getStartDate(), 'to' => $absence->getEndDate()];
        }
        usort($mates, static fn (User $a, User $b) => strcmp($a->getFullName(), $b->getFullName()));

        return array_map(static function (User $u) use ($periods, $today) {
            $list = $periods[$u->getId()] ?? [];
            $away = array_filter($list, static fn (array $p) => $p['from'] <= $today && $p['to'] >= $today);

            return ['user' => $u, 'available' => [] === $away, 'periods' => $list];
        }, $mates);
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
