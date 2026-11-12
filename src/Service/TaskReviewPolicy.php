<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\AssigneeStatus;
use App\Enum\CompletionMode;
use App\Enum\ReviewMode;
use App\Enum\ReviewOutcome;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Repository\TeamMemberRepository;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserRepository;
use App\Security\TeamScope;

/**
 * JEDINO mesto gde se odlučuje da li zadatak ide na proveru i ko sme da ga potvrdi.
 *
 * Kad zadatak ispuni uslov za završetak (SPEC 4.1):
 *  a) posebna potvrda (reviewMode SPECIAL, ili snapshot proveravača): NA_PROVERI, potvrđuju samo proveravači sa zadatka;
 *  b) reviewMode AUTO: ako je bar jedan učesnik koji je završio svoj deo na poziciji sa requiresTaskReview, NA_PROVERI
 *     i potvrđuje šef tima tog učesnika ili admin (pozicija se čita u trenutku slanja);
 *  c) inače (NONE, ili AUTO bez učesnika sa pozicijom koja traži proveru): odmah ZAVRSENO bez provere.
 * Pozicija ne daje prava: ovde određuje samo da li postoji provera.
 */
class TaskReviewPolicy
{
    public function __construct(
        private readonly TeamMemberRepository $members,
        private readonly UserRepository $users,
        private readonly UserAbsenceRepository $absences,
    ) {
    }

    public function decide(Task $task): ReviewOutcome
    {
        if ($task->isSpecialReview()) {
            return ReviewOutcome::SPECIAL;
        }
        if (ReviewMode::NONE === $task->getReviewMode()) {
            return ReviewOutcome::NONE;
        }
        foreach ($task->getAssignees() as $assignee) {
            if (AssigneeStatus::ZAVRSIO === $assignee->getStatus() && $assignee->getUser()->getPosition()?->requiresTaskReview()) {
                return ReviewOutcome::BY_POSITION;
            }
        }

        return ReviewOutcome::NONE;
    }

    /** Proveravač posebne potvrde: aktivan korisnik sa ROLE_ADMIN ili ROLE_SUPERADMIN */
    public static function isEligibleApprover(User $user): bool
    {
        return $user->isActive() && \in_array($user->getHighestRole(), [Role::ADMIN, Role::SUPERADMIN], true);
    }

    /**
     * Proveravači posebne potvrde: oni iz snapshot-a koji su još aktivni admini. Ako nijedan ne ostane, superadmini.
     *
     * @return list<User>
     */
    public function specialApprovers(Task $task): array
    {
        $approvers = array_values(array_filter($task->getRequiredApprovers()->toArray(), static fn (User $u) => self::isEligibleApprover($u)));

        return [] !== $approvers ? $approvers : $this->activeSuperadmins();
    }

    /** Snapshot ostao bez ijednog važećeg proveravača, pa potvrđuje superadmin */
    public function fallsBackToSuperadmin(Task $task): bool
    {
        foreach ($task->getRequiredApprovers() as $user) {
            if (self::isEligibleApprover($user)) {
                return false;
            }
        }

        return $task->isSpecialReview();
    }

    /**
     * Posebna potvrda: proveravač ili superadmin (poslednja instanca). Niko ne potvrđuje sopstveni zadatak,
     * osim superadmina kad nema nikog drugog ko može.
     */
    public function canApproveSpecial(Task $task, User $user): bool
    {
        $isSuper = Role::SUPERADMIN === $user->getHighestRole();
        $isReviewer = \in_array($user, $this->specialApprovers($task), true);
        if (!$user->isActive() || (!$isReviewer && !$isSuper)) {
            return false;
        }
        if (!$task->hasAssignee($user)) {
            return true;
        }
        foreach (array_merge($this->specialApprovers($task), $this->activeSuperadmins()) as $other) {
            if ($other !== $user && !$task->hasAssignee($other)) {
                return false;
            }
        }

        return $isSuper;
    }

    /**
     * Ko treba da dobije obaveštenje da zadatak čeka potvrdu (TASK_SUBMITTED).
     *
     * @return list<User>
     */
    public function reviewersFor(Task $task): array
    {
        if ($task->isSpecialReview()) {
            $others = array_values(array_filter($this->specialApprovers($task), static fn (User $u) => !$task->hasAssignee($u)));

            return [] !== $others ? $others : $this->specialApprovers($task);
        }

        return $this->positionReviewers($task);
    }

    /**
     * Provera po poziciji: šefovi timova učesnika čija pozicija traži proveru (šef nije učesnik, nije odsutan), a ako ih nema, admini.
     *
     * @return list<User>
     */
    public function positionReviewers(Task $task, bool $onlyFinished = true): array
    {
        $leaders = [];
        $today = new \DateTimeImmutable('today');
        foreach ($task->getAssignees() as $assignee) {
            $user = $assignee->getUser();
            if (!$user->getPosition()?->requiresTaskReview() || ($onlyFinished && AssigneeStatus::ZAVRSIO !== $assignee->getStatus())) {
                continue;
            }
            $team = $this->members->findActiveByUser($user)?->getTeam();
            $leader = null !== $team && !$team->isDeleted() ? $team->getLeader() : null;
            if (null !== $leader && $leader->isActive() && !$task->hasAssignee($leader) && TeamScope::covers($leader, $user)
                && null === $this->absences->findApprovedCovering($leader, $today)) {
                $leaders[spl_object_id($leader)] = $leader;
            }
        }

        return [] !== $leaders ? array_values($leaders) : $this->users->findActiveAdmins();
    }

    /**
     * Tekst za značku na zadatku od samog početka: "Proverava: šef tima", "Posebna potvrda: Ime", "Bez provere".
     * Za AUTO se gleda pozicija svih trenutnih učesnika.
     *
     * @return array{kind: string, text: string}
     */
    public function describe(Task $task): array
    {
        if ($task->isSpecialReview()) {
            $names = implode(', ', array_map(static fn (User $u) => $u->getFullName(), $this->specialApprovers($task)));
            $text = 'Posebna potvrda: '.$names;
            if ($task->isReviewFromJob()) {
                $text .= ' (zahteva posao)';
            } elseif (null !== $task->getReviewDecidedBy()) {
                $text .= ', zatražio: '.$task->getReviewDecidedBy()->getFullName();
            }

            return ['kind' => 'special', 'text' => $text];
        }
        if (ReviewMode::NONE === $task->getReviewMode()) {
            $text = 'Bez provere'.(null !== $task->getReviewDecidedBy() ? ', odlučio: '.$task->getReviewDecidedBy()->getFullName() : '');

            return ['kind' => 'none', 'text' => $text];
        }
        foreach ($task->getAssignees() as $assignee) {
            if ($assignee->getUser()->getPosition()?->requiresTaskReview()) {
                $leaders = array_filter($this->positionReviewers($task, false), static fn (User $u) => Role::SEF === $u->getHighestRole());

                return ['kind' => 'position', 'text' => 'Proverava: '.([] !== $leaders ? 'šef tima' : 'admin')];
            }
        }

        return ['kind' => 'none', 'text' => 'Bez provere'];
    }

    /**
     * Da li bi "Moj deo je gotov" od $user završio zadatak odmah, bez provere: zadatak bi tim potezom ispunio uslov za završetak,
     * a ishod bi bio NONE (za pregled u SweetAlert-u pre klika; pravo odlučivanje je decide() posle upisa statusa).
     */
    public function wouldFinishWithoutReview(Task $task, User $user): bool
    {
        $total = $task->getAssignees()->count();
        $finished = 0;
        $needsReview = false;
        foreach ($task->getAssignees() as $assignee) {
            $done = AssigneeStatus::ZAVRSIO === $assignee->getStatus() || $assignee->getUser() === $user;
            $finished += $done ? 1 : 0;
            $needsReview = $needsReview || ($done && $assignee->getUser()->getPosition()?->requiresTaskReview());
        }
        $complete = $total > 0 && (CompletionMode::ALL === $task->getCompletionMode() ? $finished === $total : $finished > 0);
        if (!$complete || $task->isSpecialReview()) {
            return false;
        }

        return ReviewMode::NONE === $task->getReviewMode() || !$needsReview;
    }

    /**
     * Izbor načina provere koji kreator sme da ponudi. Prazna lista znači da je polje skriveno i da je uvek AUTO
     * (kreator čija pozicija traži proveru). Zadatak sebi: AUTO ili SPECIAL; zadatak drugome: AUTO, NONE ili SPECIAL.
     *
     * @param list<User> $assignees
     *
     * @return list<ReviewMode>
     */
    public function modesFor(User $actor, array $assignees): array
    {
        if ($actor->getPosition()?->requiresTaskReview()) {
            return [];
        }
        $forSelf = [] === array_filter($assignees, static fn (User $u) => $u !== $actor);

        return $forSelf ? [ReviewMode::AUTO, ReviewMode::SPECIAL] : [ReviewMode::AUTO, ReviewMode::NONE, ReviewMode::SPECIAL];
    }

    /**
     * Da li $actor sme da menja način provere postojećeg zadatka (pored TASK_EDIT): zadatak nije na proveri, završen ni otkazan,
     * nije zaključan poslom, a akter je admin+, šef nadležan za SVE učesnike, ili kreator koji sme da bira.
     */
    public function canChangeMode(Task $task, User $actor): bool
    {
        if ($task->isReviewFromJob() || !$task->getStatus()->isWorkable() || $actor->getPosition()?->requiresTaskReview()) {
            return false;
        }
        $role = $actor->getHighestRole();
        if (\in_array($role, [Role::ADMIN, Role::SUPERADMIN], true)) {
            return true;
        }
        $assignees = $task->getAssigneeUsers();
        if (Role::SEF === $role && $this->leadsAll($actor, $assignees)) {
            return true;
        }

        return $task->getCreatedBy() === $actor && [] !== $this->modesFor($actor, $assignees);
    }

    /** @param list<User> $assignees */
    private function leadsAll(User $leader, array $assignees): bool
    {
        foreach ($assignees as $user) {
            if (!$this->users->isInLeaderScope($leader, $user)) {
                return false;
            }
        }

        return [] !== $assignees;
    }

    /** @return list<User> */
    private function activeSuperadmins(): array
    {
        return array_values(array_filter($this->users->findActiveAdmins(), static fn (User $u) => Role::SUPERADMIN === $u->getHighestRole()));
    }
}
