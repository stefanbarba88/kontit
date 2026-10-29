<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\HelpGuide;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * HELP_VIEW (HelpGuide): uputstvo vidi ko ima njegovu rolu, uz role_hierarchy.
 * Radnik vidi uputstvo za radnika, šef i ono za šefa, a admin i superadmin sva.
 */
class HelpVoter extends Voter
{
    public const VIEW = 'HELP_VIEW';

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof HelpGuide;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var HelpGuide $subject */
        return $token->getUser() instanceof User
            && \in_array($subject->role()->value, $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true);
    }
}
