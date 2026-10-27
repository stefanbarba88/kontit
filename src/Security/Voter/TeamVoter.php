<?php

namespace App\Security\Voter;

use App\Entity\Team;
use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * TEAM_MANAGE (bez subjekta ili Team): samo admin+.
 * TEAM_MEMBERS_VIEW (Team): admin+ ili šef tog tima. Šef ne vidi druge timove.
 */
class TeamVoter extends Voter
{
    public const MANAGE = 'TEAM_MANAGE';
    public const MEMBERS_VIEW = 'TEAM_MEMBERS_VIEW';

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::MANAGE => null === $subject || $subject instanceof Team,
            self::MEMBERS_VIEW => $subject instanceof Team,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $reachable = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
        if (in_array(Role::ADMIN->value, $reachable, true)) {
            return true;
        }

        return self::MEMBERS_VIEW === $attribute
            && $subject instanceof Team
            && !$subject->isDeleted()
            && $subject->getLeader() === $user;
    }
}
