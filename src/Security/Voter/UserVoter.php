<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * USER_MANAGE (bez subjekta): lista, kreiranje korisnika.
 * USER_VIEW / USER_EDIT (subjekt User): admin+, uz pravilo da admine i superadmine menja samo superadmin.
 * USER_TASKS_VIEW (subjekt User): tab "Zadaci" korisnika: sam korisnik, šef za radnika iz svog tima (TeamScope), admin+ za sve.
 */
class UserVoter extends Voter
{
    public const MANAGE = 'USER_MANAGE';
    public const VIEW = 'USER_VIEW';
    public const EDIT = 'USER_EDIT';
    public const TASKS_VIEW = 'USER_TASKS_VIEW';

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly UserRepository $users,
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::MANAGE => null === $subject,
            self::VIEW, self::EDIT, self::TASKS_VIEW => $subject instanceof User,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User) {
            return false;
        }

        // izmena korisnika (lozinka, mejl, rola, pozicija, deaktivacija) nikad iz "Prikaži kao korisnik" (SPEC 8s)
        if ($token instanceof SwitchUserToken && \in_array($attribute, [self::MANAGE, self::EDIT], true)) {
            return false;
        }

        $reachable = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
        $isAdmin = in_array(Role::ADMIN->value, $reachable, true);
        $isSuperadmin = in_array(Role::SUPERADMIN->value, $reachable, true);

        if (self::TASKS_VIEW === $attribute) {
            // admin+ svi; ostali sebe, a šef i radnike iz svog tima
            return $isAdmin || $actor === $subject
                || (in_array(Role::SEF->value, $reachable, true) && $this->users->isInLeaderScope($actor, $subject));
        }

        if (!$isAdmin) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE, self::VIEW => true,
            self::EDIT => $isSuperadmin || !in_array($subject->getHighestRole(), [Role::ADMIN, Role::SUPERADMIN], true),
            default => false,
        };
    }
}
