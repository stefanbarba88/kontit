<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * USER_IMPERSONATE (subjekt User): "Prikaži kao korisnik" (SPEC 8s). Isto pravilo koristi switch_user u security.yaml, dugmad u Twigu i testovi.
 *  1. pokretač ima ROLE_ADMIN ili višu (preko role_hierarchy),
 *  2. meta je STROGO niža: sve što meta dostiže u hijerarhiji dostiže i pokretač, a pokretač dostiže bar jednu rolu više.
 *     Ne nabrajamo role, pa isto važi i kad se u hijerarhiju doda nova rola,
 *  3. nikad na sebe, na deaktiviranog korisnika, ni iz impersonacije (nema ugnježdavanja).
 */
class ImpersonationVoter extends Voter
{
    public const IMPERSONATE = 'USER_IMPERSONATE';

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::IMPERSONATE === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User || !$subject instanceof User || $token instanceof SwitchUserToken) {
            return false;
        }
        if ($actor === $subject || (null !== $actor->getId() && $actor->getId() === $subject->getId()) || !$subject->isActive()) {
            return false;
        }

        $actorRoles = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
        if (!\in_array(Role::ADMIN->value, $actorRoles, true)) {
            return false;
        }
        $targetRoles = $this->roleHierarchy->getReachableRoleNames($subject->getRoles());

        return [] === array_diff($targetRoles, $actorRoles) && \count($targetRoles) < \count($actorRoles);
    }
}
