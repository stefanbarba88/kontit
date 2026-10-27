<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * CATALOG_MANAGE: katalog poslova, paketi, praznici, generisanje zadataka. Samo admin+.
 * PRICE_VIEW: cene i finansijska analitika. Samo admin+ (šef i radnik ne vide ni cene ni dogovorene iznose).
 */
class CatalogVoter extends Voter
{
    public const MANAGE = 'CATALOG_MANAGE';
    public const PRICE_VIEW = 'PRICE_VIEW';

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MANAGE, self::PRICE_VIEW], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof User
            && \in_array(Role::ADMIN->value, $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true);
    }
}
