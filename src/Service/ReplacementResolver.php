<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\User;
use App\Repository\UserAbsenceRepository;

/**
 * Odgovara na pitanje "koga ja trenutno zamenjujem na ovom zadatku" (SPEC 5).
 * Zamena važi kad je zadatak vezan za klijenta kome je $backup backup, a glavni zaduženi
 * je učesnik zadatka i ima odobreno odsustvo koje pokriva današnji dan.
 */
class ReplacementResolver
{
    public function __construct(private readonly UserAbsenceRepository $absences)
    {
    }

    /**
     * @return list<TaskAssignee>
     */
    public function substitutedAssignees(Task $task, User $backup): array
    {
        $client = $task->getClient();
        if (null === $client || !$client->isActive() || $client->getBackupAccountant() !== $backup) {
            return [];
        }

        $primary = $client->getPrimaryAccountant();
        $assignee = $task->findAssignee($primary);
        if (null === $assignee || $primary === $backup || null === $this->absences->findApprovedCovering($primary, new \DateTimeImmutable('today'))) {
            return [];
        }

        return [$assignee];
    }

    public function isSubstitute(Task $task, User $backup): bool
    {
        return [] !== $this->substitutedAssignees($task, $backup);
    }
}
