<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskComment;
use App\Entity\User;
use App\Enum\AssigneeStatus;
use App\Enum\CommentType;
use App\Enum\CompletionMode;
use App\Enum\NotificationType;
use App\Enum\TaskStatus;
use App\Repository\TeamMemberRepository;
use App\Repository\UserRepository;
use App\Security\TeamScope;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Statusi učesnika, prelaz zadatka na proveru (SPEC 4.1), potvrda i vraćanje na doradu (SPEC 4.3).
 * Ne proverava prava (to rade voteri), nego samo pravila stanja.
 */
class TaskCompletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notifications,
        private readonly TeamMemberRepository $members,
        private readonly UserRepository $users,
        private readonly ReplacementResolver $replacements,
    ) {
    }

    /**
     * Ko može da potvrdi zadatak: šefovi timova učesnika koji sami nisu učesnici, a ako takvih nema, admini.
     *
     * @return list<User>
     */
    public function approversFor(Task $task): array
    {
        $leaders = [];
        foreach ($task->getAssigneeUsers() as $assignee) {
            $team = $this->members->findActiveByUser($assignee)?->getTeam();
            $leader = null !== $team && !$team->isDeleted() ? $team->getLeader() : null;
            if (null !== $leader && $leader->isActive() && !$task->hasAssignee($leader) && TeamScope::covers($leader, $assignee)) {
                $leaders[spl_object_id($leader)] = $leader;
            }
        }

        return [] !== $leaders ? array_values($leaders) : $this->users->findActiveAdmins();
    }

    /**
     * Obaveštava one koji potvrđuju da je zadatak stigao na proveru. Poziva se posle flush-a.
     */
    public function notifySubmitted(Task $task, ?User $actor = null): void
    {
        $this->notifications->notifyMany(
            $this->approversFor($task),
            NotificationType::TASK_SUBMITTED,
            'Zadatak čeka potvrdu',
            sprintf('Zadatak „%s“ je završen i čeka tvoju potvrdu.', $task->getTitle()),
            ['task_show', ['id' => $task->getId()]],
            $actor,
        );
    }

    /**
     * Učesnik počinje rad ("U radu"). Zadatak se automatski prebacuje u U_RADU.
     */
    public function start(Task $task, User $user): void
    {
        $assignee = $this->requireAssignee($task, $user);
        $this->assertWorkable($task);

        if (AssigneeStatus::NOVO === $assignee->getStatus()) {
            $assignee->setStatus(AssigneeStatus::U_RADU);
        }
        $this->touchInProgress($task);
        $this->em->flush();
    }

    /**
     * "Moj deo je gotov". Ne može dok postoje neštiklirane stavke dodeljene tom učesniku.
     */
    public function markDone(Task $task, User $user, ?string $note = null): void
    {
        $assignee = $this->requireAssignee($task, $user);
        $this->assertWorkable($task);

        foreach ($task->getChecklistItems() as $item) {
            if (!$item->isDone() && $item->getAssignedTo() === $user) {
                throw new \DomainException('Imaš neštikliranih stavki checkliste koje su dodeljene vama.');
            }
        }

        $before = $task->getStatus();
        $assignee->setStatus(AssigneeStatus::ZAVRSIO)->setCompletedAt(new \DateTimeImmutable())->setNote($note);
        $this->evaluate($task);
        $this->em->flush();

        if (TaskStatus::NA_PROVERI !== $before && TaskStatus::NA_PROVERI === $task->getStatus()) {
            $this->notifySubmitted($task, $user);
        }
    }

    /**
     * Zamena (backup) završava deo odsutnog glavnog zaduženog. Isti uslovi kao u markDone().
     * U istoriji ostaje da je to uradila zamena (ActivityLogListener).
     */
    public function markDoneAsSubstitute(Task $task, User $substitute, ?string $note = null): void
    {
        $substituted = $this->replacements->substitutedAssignees($task, $substitute);
        if ([] === $substituted) {
            throw new \DomainException('Ne zamenjuješ nijednog učesnika ovog zadatka.');
        }
        $this->assertWorkable($task);

        foreach ($substituted as $assignee) {
            foreach ($task->getChecklistItems() as $item) {
                if (!$item->isDone() && $item->getAssignedTo() === $assignee->getUser()) {
                    throw new \DomainException('Ima neštikliranih stavki koje su dodeljene kolegi koga zamenjuješ.');
                }
            }
        }

        $before = $task->getStatus();
        foreach ($substituted as $assignee) {
            $assignee->setStatus(AssigneeStatus::ZAVRSIO)->setCompletedAt(new \DateTimeImmutable())->setNote($note);
        }
        $this->evaluate($task);
        $this->em->flush();

        if (TaskStatus::NA_PROVERI !== $before && TaskStatus::NA_PROVERI === $task->getStatus()) {
            $this->notifySubmitted($task, $substitute);
        }
    }

    /**
     * Povlačenje "moj deo je gotov", dok zadatak nije potvrđen.
     */
    public function undoDone(Task $task, User $user): void
    {
        $assignee = $this->requireAssignee($task, $user);
        if (!in_array($task->getStatus(), [TaskStatus::NA_PROVERI, ...$this->workable()], true)) {
            throw new \DomainException('Zadatak je već zatvoren.');
        }

        $assignee->setStatus(AssigneeStatus::U_RADU)->setCompletedAt(null);
        $this->evaluate($task);
        $this->em->flush();
    }

    /**
     * Posle promene učesnika ili režima: proverava da li zadatak ide na proveru ili se vraća u rad.
     * Ne radi flush.
     */
    public function evaluate(Task $task): void
    {
        $assignees = $task->getAssignees();
        $finished = $task->getFinishedAssigneesCount();
        $complete = $assignees->count() > 0 && match ($task->getCompletionMode()) {
            CompletionMode::ALL => $finished === $assignees->count(),
            CompletionMode::ANY => $finished > 0,
        };

        if ($complete && $task->getStatus()->isWorkable()) {
            $task->setStatus(TaskStatus::NA_PROVERI)->setSubmittedAt(new \DateTimeImmutable());
        } elseif (!$complete && TaskStatus::NA_PROVERI === $task->getStatus()) {
            $task->setStatus(TaskStatus::U_RADU)->setSubmittedAt(null);
        }
    }

    public function approve(Task $task, User $approver): void
    {
        if (TaskStatus::NA_PROVERI !== $task->getStatus()) {
            throw new \DomainException('Zadatak nije na proveri.');
        }

        $task->setStatus(TaskStatus::ZAVRSENO)->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->notifications->notifyMany(
            $task->getAssigneeUsers(),
            NotificationType::TASK_APPROVED,
            'Zadatak je potvrđen',
            sprintf('Zadatak „%s“ je potvrđen i završen.', $task->getTitle()),
            ['task_show', ['id' => $task->getId()]],
            $approver,
        );
    }

    /**
     * Vraćanje na doradu: obavezan razlog (čuva se kao komentar), svi učesnici se vraćaju u U_RADU.
     */
    public function returnForRework(Task $task, User $reviewer, string $reason, bool $email = false): void
    {
        if (TaskStatus::NA_PROVERI !== $task->getStatus()) {
            throw new \DomainException('Zadatak nije na proveri.');
        }
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \DomainException('Unesi razlog vraćanja na doradu.');
        }

        $this->em->wrapInTransaction(function () use ($task, $reviewer, $reason): void {
            $this->em->persist(new TaskComment($task, $reviewer, $reason, CommentType::VRACANJE_NA_DORADU));
            $this->resetToInProgress($task);
        });

        $this->notifications->notifyMany(
            $task->getAssigneeUsers(),
            NotificationType::TASK_RETURNED,
            'Zadatak je vraćen na doradu',
            sprintf("Zadatak „%s“ je vraćen na doradu.\nRazlog: %s", $task->getTitle(), $reason),
            ['task_show', ['id' => $task->getId()]],
            $reviewer,
            $email,
        );
    }

    /**
     * Posle potvrde ili prijave za proveru neko je promenio stavku (odštiklirao ili dodao): zadatak se ponovo otvara.
     * Ne radi flush.
     */
    public function reopen(Task $task): void
    {
        if (in_array($task->getStatus(), [TaskStatus::NA_PROVERI, TaskStatus::ZAVRSENO], true)) {
            $this->resetToInProgress($task);
        }
    }

    /**
     * Prvo štikliranje (ili promena statusa učesnika) prebacuje zadatak i učesnika u rad. Ne radi flush.
     */
    public function touchInProgress(Task $task, ?User $by = null): void
    {
        if (null !== $by && null !== ($assignee = $task->findAssignee($by)) && AssigneeStatus::NOVO === $assignee->getStatus()) {
            $assignee->setStatus(AssigneeStatus::U_RADU);
        }
        if (TaskStatus::NOVO === $task->getStatus()) {
            $task->setStatus(TaskStatus::U_RADU);
        }
    }

    private function resetToInProgress(Task $task): void
    {
        $task->setStatus(TaskStatus::U_RADU)->setSubmittedAt(null)->setApprovedBy(null)->setApprovedAt(null);
        foreach ($task->getAssignees() as $assignee) {
            $assignee->setStatus(AssigneeStatus::U_RADU)->setCompletedAt(null);
        }
    }

    private function requireAssignee(Task $task, User $user): \App\Entity\TaskAssignee
    {
        return $task->findAssignee($user) ?? throw new \DomainException('Nisi učesnik ovog zadatka.');
    }

    private function assertWorkable(Task $task): void
    {
        if (!$task->getStatus()->isWorkable()) {
            throw new \DomainException('U ovom zadatku se trenutno ne radi (status: '.$task->getStatus()->label().').');
        }
    }

    /**
     * @return list<TaskStatus>
     */
    private function workable(): array
    {
        return [TaskStatus::NOVO, TaskStatus::U_RADU, TaskStatus::CEKA_KLIJENTA];
    }
}
