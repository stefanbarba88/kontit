<?php

namespace App\Service;

use App\Entity\ChecklistItem;
use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\TaskComment;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class TaskService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly TaskCompletionService $completion,
        private readonly NotificationService $notifications,
        private readonly ReplacementResolver $replacements,
        private readonly ClientPeople $clientPeople,
    ) {
    }

    public function isAdmin(User $user): bool
    {
        return $this->hasRole($user, Role::ADMIN);
    }

    public function isLeader(User $user): bool
    {
        return $this->hasRole($user, Role::SEF);
    }

    /**
     * Ko može da bude učesnik (SPEC 4.4): radnik samo sebe, šef sebe i svoj tim, admin+ bilo koga.
     */
    public function canAssignTo(User $actor, User $user): bool
    {
        if ($this->isAdmin($actor)) {
            return true;
        }

        return $this->isLeader($actor) ? $this->users->isInLeaderScope($actor, $user) : $actor === $user;
    }

    /**
     * @param list<User> $assignees
     * @param bool       $generated zadatak pravi sistem iz ugovorenog posla (zaduženi je već određen pravilima generisanja)
     */
    public function create(Task $task, array $assignees, User $actor, bool $email = false, bool $generated = false): void
    {
        $assignees = $this->uniqueUsers($assignees);
        if (!$generated) {
            $this->validateAssignees($task, $assignees, $actor);
        }
        $this->validateClient($task);

        $this->em->wrapInTransaction(function () use ($task, $assignees, $actor): void {
            $task->setCreatedBy($actor);
            foreach ($assignees as $user) {
                $task->addAssignee(new TaskAssignee($task, $user));
            }
            $this->em->persist($task);
        });

        $this->notifyAssigned($task, $assignees, $actor, $email);
    }

    /**
     * Čuva izmene zadatka i usklađuje listu učesnika. Učesnik koji ostaje zadržava svoj status.
     *
     * @param list<User> $assignees
     */
    public function update(Task $task, array $assignees, User $actor, bool $email = false): void
    {
        $assignees = $this->uniqueUsers($assignees);
        $this->validateAssignees($task, $assignees, $actor);
        $this->validateClient($task);

        $added = array_values(array_filter($assignees, fn (User $u) => !$task->hasAssignee($u)));
        $before = $task->getStatus();

        $this->em->wrapInTransaction(function () use ($task, $assignees): void {
            foreach ($task->getAssignees()->toArray() as $existing) {
                if (!in_array($existing->getUser(), $assignees, true)) {
                    foreach ($task->getChecklistItems() as $item) {
                        if ($item->getAssignedTo() === $existing->getUser()) {
                            $item->setAssignedTo(null);
                        }
                    }
                    $task->removeAssignee($existing);
                }
            }
            foreach ($assignees as $user) {
                if (!$task->hasAssignee($user)) {
                    $task->addAssignee(new TaskAssignee($task, $user));
                }
            }
            // novi učesnik u režimu ALL znači da zadatak nije više kompletan, a izlazak učesnika može da ga kompletira
            $this->completion->evaluate($task);
            $this->em->flush();
        });

        $this->notifyAssigned($task, $added, $actor, $email);
        if (TaskStatus::NA_PROVERI !== $before && TaskStatus::NA_PROVERI === $task->getStatus()) {
            $this->completion->notifySubmitted($task, $actor);
        }
    }

    public function cancel(Task $task, string $reason, User $actor): void
    {
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \DomainException('Razlog otkazivanja je obavezan.');
        }

        $task->setStatus(TaskStatus::OTKAZANO);
        $this->em->flush();
        // razlog ostaje zapisan kao komentar zadatka
        $this->addComment($task, $actor, 'Zadatak je otkazan. Razlog: '.$reason);
    }

    /**
     * "Čeka klijenta" i povratak u rad.
     */
    public function setWaitingForClient(Task $task, bool $waiting): void
    {
        if ($waiting) {
            if (!in_array($task->getStatus(), [TaskStatus::NOVO, TaskStatus::U_RADU], true)) {
                throw new \DomainException('Samo zadatak koji je u radu može da čeka klijenta.');
            }
            $task->setStatus(TaskStatus::CEKA_KLIJENTA);
        } else {
            if (TaskStatus::CEKA_KLIJENTA !== $task->getStatus()) {
                throw new \DomainException('Zadatak ne čeka klijenta.');
            }
            $task->setStatus(TaskStatus::U_RADU);
        }
        $this->em->flush();
    }

    public function addChecklistItem(Task $task, string $text, ?User $assignedTo, User $actor): ChecklistItem
    {
        $text = trim($text);
        if ('' === $text || mb_strlen($text) > 255) {
            throw new \DomainException('Tekst stavke mora imati između 1 i 255 karaktera.');
        }
        if (null !== $assignedTo && !$task->hasAssignee($assignedTo)) {
            throw new \DomainException('Stavku možeš dodeliti samo učesniku zadatka.');
        }

        $position = 0;
        foreach ($task->getChecklistItems() as $existing) {
            $position = max($position, $existing->getPosition() + 1);
        }

        $item = new ChecklistItem($task, $text, $position, $assignedTo);
        $task->addChecklistItem($item);
        $this->completion->reopen($task);
        $this->em->flush();

        return $item;
    }

    public function setChecklistItemDone(ChecklistItem $item, bool $done, User $actor): void
    {
        $task = $item->getTask();
        if (!$this->canToggleItem($item, $actor)) {
            throw new \DomainException('Ovu stavku može da štiklira samo '.$item->getAssignedTo()?->getFullName().'.');
        }

        if ($done && !$item->isDone()) {
            $item->markDone($actor);
            $this->completion->touchInProgress($task, $actor);
        } elseif (!$done && $item->isDone()) {
            $item->markUndone();
            $this->completion->reopen($task);
        }
        $this->em->flush();
    }

    /**
     * Zajedničku stavku štiklira bilo ko ko radi na zadatku, a dodeljenu samo ta osoba, njena zamena, šef, admin ili kreator.
     */
    public function canToggleItem(ChecklistItem $item, User $actor): bool
    {
        $assigned = $item->getAssignedTo();
        if (null === $assigned || $assigned === $actor) {
            return true;
        }

        foreach ($this->replacements->substitutedAssignees($item->getTask(), $actor) as $substituted) {
            if ($substituted->getUser() === $assigned) {
                return true;
            }
        }

        return $this->isAdmin($actor) || $item->getTask()->getCreatedBy() === $actor || $this->isLeader($actor);
    }

    public function assignChecklistItem(ChecklistItem $item, ?User $user): void
    {
        if (null !== $user && !$item->getTask()->hasAssignee($user)) {
            throw new \DomainException('Stavku možeš dodeliti samo učesniku zadatka.');
        }
        $item->setAssignedTo($user);
        $this->em->flush();
    }

    public function deleteChecklistItem(ChecklistItem $item): void
    {
        $item->getTask()->removeChecklistItem($item);
        $this->em->remove($item);
        $this->em->flush();
    }

    /**
     * @param list<int> $orderedIds ID-evi stavki u novom redosledu
     */
    public function reorderChecklist(Task $task, array $orderedIds): void
    {
        $position = 0;
        $byId = [];
        foreach ($task->getChecklistItems() as $item) {
            $byId[$item->getId()] = $item;
        }
        foreach ($orderedIds as $id) {
            if (isset($byId[$id])) {
                $byId[$id]->setPosition($position++);
                unset($byId[$id]);
            }
        }
        // stavke koje klijent nije poslao ostaju na kraju
        foreach ($byId as $item) {
            $item->setPosition($position++);
        }
        $this->em->flush();
    }

    public function addComment(Task $task, User $author, string $body): TaskComment
    {
        $body = trim($body);
        if ('' === $body) {
            throw new \DomainException('Komentar ne može biti prazan.');
        }

        $comment = new TaskComment($task, $author, $body);
        $this->em->persist($comment);
        $this->em->flush();

        $this->notifications->notifyMany(
            array_merge($task->getAssigneeUsers(), [$task->getCreatedBy()]),
            NotificationType::TASK_COMMENTED,
            'Novi komentar',
            sprintf("%s je komentarisao zadatak „%s“:
%s", $author->getFullName(), $task->getTitle(), mb_strimwidth($body, 0, 200, '…')),
            ['task_show', ['id' => $task->getId()]],
            $author,
        );

        return $comment;
    }

    public function editComment(TaskComment $comment, User $actor, string $body): void
    {
        if (!$comment->isEditableBy($actor)) {
            throw new \DomainException(sprintf('Komentar možeš izmeniti samo prvih %d minuta posle objave.', TaskComment::EDIT_WINDOW_MINUTES));
        }
        $body = trim($body);
        if ('' === $body) {
            throw new \DomainException('Komentar ne može biti prazan.');
        }

        $comment->edit($body);
        $this->em->flush();
    }

    /**
     * @param list<User> $recipients
     */
    private function notifyAssigned(Task $task, array $recipients, User $actor, bool $email): void
    {
        $urgent = TaskPriority::HITAN === $task->getPriority();
        $this->notifications->notifyMany(
            $recipients,
            NotificationType::TASK_ASSIGNED,
            $urgent ? 'Novi HITAN zadatak' : 'Novi zadatak',
            sprintf('Dodeljen ti je zadatak „%s“%s%s. Dodelio: %s.', $task->getTitle(), null !== $task->getClient() ? ' ('.$task->getClient()->getShortName().')' : '', null !== $task->getDueDate() ? ', rok '.$task->getDueDate()->format('d.m.Y.') : '', $actor->getFullName()),
            ['task_show', ['id' => $task->getId()]],
            $actor,
            $email,
        );
    }

    /**
     * Neizmenjeni učesnici (već na zadatku) ne proveravaju se ponovo, jer ih je neko ranije mogao imati u nadležnosti.
     *
     * @param list<User> $assignees
     */
    private function validateAssignees(Task $task, array $assignees, User $actor): void
    {
        if ([] === $assignees) {
            throw new \DomainException('Zadatak mora imati bar jednog učesnika.');
        }

        foreach ($assignees as $user) {
            if ($task->hasAssignee($user)) {
                continue;
            }
            if (!$user->isActive()) {
                throw new \DomainException(sprintf('%s je deaktiviran i ne može dobiti zadatak.', $user->getFullName()));
            }
            if (!$this->canAssignTo($actor, $user)) {
                throw new \DomainException(sprintf('Ne možeš dodeliti zadatak korisniku %s.', $user->getFullName()));
            }
            // zadatak klijenta dobijaju samo ljudi raspoređeni na tog klijenta (SPEC 8i.3)
            if (null !== $task->getClient() && !$this->clientPeople->isAssigned($task->getClient(), $user)) {
                throw new \DomainException(sprintf('%s nije raspoređen na klijenta %s (glavni, zamena ili zaduženi na nekom poslu).', $user->getFullName(), $task->getClient()->getShortName()));
            }
        }
    }

    private function validateClient(Task $task): void
    {
        $client = $task->getClient();
        if (null !== $client && !$client->isActive() && null === $task->getId()) {
            throw new \DomainException('Klijent je deaktiviran.');
        }
        $job = $task->getClientJob();
        if (null !== $job && $job->getClient() !== $client) {
            throw new \DomainException('Izabrani posao nije ugovoren sa ovim klijentom.');
        }
    }

    /**
     * @param list<User> $users
     *
     * @return list<User>
     */
    private function uniqueUsers(array $users): array
    {
        $unique = [];
        foreach ($users as $user) {
            $unique[spl_object_id($user)] = $user;
        }

        return array_values($unique);
    }

    private function hasRole(User $user, Role $role): bool
    {
        return in_array($role->value, $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true);
    }
}
