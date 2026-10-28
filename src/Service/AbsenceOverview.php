<?php

namespace App\Service;

use App\Entity\AbsenceType;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Repository\AbsenceTypeRepository;
use App\Repository\TeamRepository;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserRepository;

/**
 * Tabovi "Pregled" (radnik × dani u mesecu) i "Evidencija" (zbir po radniku i vrsti za godinu) na stranici Odsustva (SPEC 8j.3).
 * Šef vidi svoj tim (i sebe), admin sve, sa filterom po timu. Isti podaci idu u izvoz (AbsenceRecordExport).
 */
class AbsenceOverview
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TeamRepository $teams,
        private readonly UserAbsenceRepository $absences,
        private readonly AbsenceTypeRepository $types,
        private readonly TaskService $tasks,
        private readonly LeaveService $leave,
        private readonly WorkingDayService $workingDays,
    ) {
    }

    /**
     * Timovi za filter: admin svi, šef oni koje vodi.
     *
     * @return list<Team>
     */
    public function teamsFor(User $me): array
    {
        $all = $this->teams->createListQueryBuilder()->getQuery()->getResult();

        return $this->tasks->isAdmin($me) ? $all : array_values(array_filter($all, static fn (Team $t) => $t->getLeader() === $me));
    }

    /**
     * Radnici u opsegu (šef: on i članovi timova koje vodi; admin: svi aktivni), opciono samo jedan tim.
     *
     * @return list<User>
     */
    public function usersFor(User $me, ?int $teamId = null): array
    {
        $users = $this->users->createAssignableQueryBuilder($me, $this->tasks->isAdmin($me))->getQuery()->getResult();
        if (null === $teamId) {
            return $users;
        }
        $team = array_values(array_filter($this->teamsFor($me), static fn (Team $t) => $t->getId() === $teamId))[0] ?? null;
        if (null === $team) {
            return $users;
        }
        $ids = [];
        foreach ($team->getActiveMembers() as $member) {
            $ids[$member->getUser()->getId()] = true;
        }
        $ids[$team->getLeader()?->getId()] = true;

        return array_values(array_filter($users, static fn (User $u) => isset($ids[$u->getId()])));
    }

    /**
     * Mesečni pregled: dani u mesecu, neradni dani i odsustvo po radniku i danu.
     *
     * @return array{days: list<\DateTimeImmutable>, nonWorking: array<string, string>, users: list<User>, cells: array<int, array<string, UserAbsence>>}
     */
    public function month(User $me, \DateTimeImmutable $month, ?int $teamId = null): array
    {
        $from = $month->modify('first day of this month');
        $to = $month->modify('last day of this month');
        $users = $this->usersFor($me, $teamId);
        $days = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $days[] = $d;
        }

        $cells = [];
        foreach ($this->absences->findInPeriod($users, $from, $to) as $absence) {
            for ($d = max($absence->getStartDate(), $from); $d <= min($absence->getEndDate(), $to); $d = $d->modify('+1 day')) {
                $cells[$absence->getUser()->getId()][$d->format('Y-m-d')] = $absence;
            }
        }

        return [
            'days' => $days,
            'nonWorking' => $this->workingDays->nonWorkingDaysBetween($from, $to),
            'users' => $users,
            'cells' => $cells,
        ];
    }

    /**
     * Evidencija za godinu: dani po vrsti i stanje godišnjeg odmora po radniku.
     *
     * @return array{types: list<AbsenceType>, rows: list<array{user: User, byType: array<int, float>, total: float, leave: array<string, mixed>}>}
     */
    public function records(User $me, int $year, ?int $teamId = null): array
    {
        $users = $this->usersFor($me, $teamId);
        $sums = $this->absences->sumsByUserAndType($users, $year);
        $rows = [];
        foreach ($users as $user) {
            $byType = $sums[$user->getId()] ?? [];
            $rows[] = [
                'user' => $user,
                'byType' => $byType,
                'total' => array_sum($byType),
                'leave' => $this->leave->balance($user, $year),
            ];
        }

        return ['types' => $this->types->findOrdered(), 'rows' => $rows];
    }
}
