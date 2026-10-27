<?php

namespace App\Twig;

use App\Entity\ChecklistItem;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\ReplacementResolver;
use App\Service\TaskService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TaskExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly TaskRepository $tasks,
        private readonly TaskService $service,
        private readonly ReplacementResolver $replacements,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('awaiting_approval_count', $this->awaitingApprovalCount(...)),
            new TwigFunction('my_overdue_count', $this->myOverdueCount(...)),
            new TwigFunction('can_toggle_item', $this->canToggleItem(...)),
            new TwigFunction('substituting_for', $this->substitutingFor(...)),
        ];
    }

    /**
     * Broj zadataka koje ulogovani šef (ili admin) treba da potvrdi, za brojač u navigaciji.
     */
    public function awaitingApprovalCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->service->isLeader($user)) {
            return 0;
        }

        return $this->tasks->countAwaitingApproval($user, $this->service->isAdmin($user));
    }

    /**
     * Broj mojih zadataka koji kasne (donja navigacija na telefonu).
     */
    public function myOverdueCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->tasks->countOverdueFor($user) : 0;
    }

    /**
     * Odsutne kolege koje ulogovani korisnik na ovom zadatku trenutno zamenjuje (za oznaku "PREUZETO").
     *
     * @return list<User>
     */
    public function substitutingFor(Task $task): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return array_map(static fn (\App\Entity\TaskAssignee $a) => $a->getUser(), $this->replacements->substitutedAssignees($task, $user));
    }

    /**
     * Da li ulogovani korisnik sme da štiklira stavku (voter TASK_COMPLETE + pravilo o dodeljenoj stavci).
     */
    public function canToggleItem(ChecklistItem $item): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User
            && $this->security->isGranted('TASK_COMPLETE', $item->getTask())
            && $this->service->canToggleItem($item, $user);
    }
}
