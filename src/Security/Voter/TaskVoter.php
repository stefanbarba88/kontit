<?php

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Repository\TeamMemberRepository;
use App\Security\TeamScope;
use App\Service\ReplacementResolver;
use App\Service\TaskReviewPolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * TASK_CREATE (bez subjekta): svako prijavljen.
 * TASK_VIEW (i komentarisanje): admin+, kreator, učesnik, šef tima čiji je član učesnik i zamena odsutnog učesnika.
 * TASK_EDIT (podaci, učesnici, struktura checkliste): admin+, kreator i šef tima, dok zadatak nije završen ni otkazan.
 * TASK_COMPLETE (štikliranje, svoj deo): admin+, učesnik, šef tima i zamena odsutnog učesnika, dok zadatak nije otkazan.
 * TASK_APPROVE: zadatak je na proveri. Posebna potvrda: samo proveravači sa zadatka i superadmin (TaskReviewPolicy::canApproveSpecial),
 *   šef tima i neizabrani admin ne mogu. Inače admin+ ili šef tima koji nije i sam učesnik.
 * TASK_CANCEL: kao TASK_EDIT.
 */
class TaskVoter extends Voter
{
    public const CREATE = 'TASK_CREATE';
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const COMPLETE = 'TASK_COMPLETE';
    public const APPROVE = 'TASK_APPROVE';
    public const CANCEL = 'TASK_CANCEL';

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly TeamMemberRepository $members,
        private readonly ReplacementResolver $replacements,
        private readonly TaskReviewPolicy $policy,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE => null === $subject,
            self::VIEW, self::EDIT, self::COMPLETE, self::APPROVE, self::CANCEL => $subject instanceof Task,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return true;
        }

        /** @var Task $subject */
        $reachable = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
        $isAdmin = in_array(Role::ADMIN->value, $reachable, true);
        $isLeader = !$isAdmin && in_array(Role::SEF->value, $reachable, true) && $this->leadsAssigneeTeam($subject, $user);
        $isAssignee = $subject->hasAssignee($user);
        $isCreator = $subject->getCreatedBy() === $user;
        $isSubstitute = !$isAdmin && !$isAssignee && $this->replacements->isSubstitute($subject, $user);
        $status = $subject->getStatus();

        return match ($attribute) {
            self::VIEW => $isAdmin || $isLeader || $isAssignee || $isCreator || $isSubstitute,
            self::EDIT, self::CANCEL => $status->isOpen() && ($isAdmin || $isLeader || $isCreator),
            self::COMPLETE => TaskStatus::OTKAZANO !== $status && ($isAdmin || $isLeader || $isAssignee || $isSubstitute),
            self::APPROVE => TaskStatus::NA_PROVERI === $status && ($subject->isSpecialReview()
                ? $this->policy->canApproveSpecial($subject, $user)
                : ($isAdmin || ($isLeader && !$isAssignee))),
            default => false,
        };
    }

    private function leadsAssigneeTeam(Task $task, User $leader): bool
    {
        foreach ($task->getAssigneeUsers() as $assignee) {
            // šef ne vidi zadatke člana tima koji je šef ili admin (TeamScope)
            if (!TeamScope::covers($leader, $assignee)) {
                continue;
            }
            $team = $this->members->findActiveByUser($assignee)?->getTeam();
            if (null !== $team && !$team->isDeleted() && $team->getLeader() === $leader) {
                return true;
            }
        }

        return false;
    }
}
