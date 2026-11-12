<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * SPECIAL_APPROVALS_VIEW: stranica Posebne potvrde, brojač u meniju i kartica na dashboardu. Samo admin i superadmin.
 * Šef tima i radnik nemaju pristup (403), čak ni kad su učesnici zadatka sa posebnom potvrdom.
 */
class SpecialApprovalVoter extends Voter
{
    public const VIEW = 'SPECIAL_APPROVALS_VIEW';

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof User
            && \in_array(Role::ADMIN->value, $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true);
    }
}
