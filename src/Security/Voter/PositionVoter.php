<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * POSITION_MANAGE: pregled i izmena šifarnika pozicija zaposlenih. Samo admin+.
 * Pozicija sama NE daje nikakva prava; prava određuje isključivo ROLE_* ("Prava pristupa").
 */
class PositionVoter extends Voter
{
    public const MANAGE = 'POSITION_MANAGE';

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof User
            && \in_array(Role::ADMIN->value, $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true);
    }
}
