<?php

namespace App\Security\Voter;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\TeamMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * CLIENT_CREATE (bez subjekta) i CLIENT_EDIT (podaci, aktivacija): admin+.
 * CLIENT_VIEW: admin+, glavni, backup i šef tima u kome je glavni ili backup (trenutno) član.
 * CLIENT_ASSIGN: admin+ i šef na klijentima svog tima (ili svojim).
 */
class ClientVoter extends Voter
{
    public const CREATE = 'CLIENT_CREATE';
    public const VIEW = 'CLIENT_VIEW';
    public const EDIT = 'CLIENT_EDIT';
    public const ASSIGN = 'CLIENT_ASSIGN';

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly TeamMemberRepository $members,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE => null === $subject,
            self::VIEW, self::EDIT, self::ASSIGN => $subject instanceof Client,
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

        if (self::CREATE === $attribute || self::EDIT === $attribute) {
            return false;
        }

        /** @var Client $subject */
        $isOwn = $subject->getPrimaryAccountant() === $user || $subject->getBackupAccountant() === $user;
        $isTeamLeader = in_array(Role::SEF->value, $reachable, true) && $this->leadsAssigneeTeam($subject, $user);

        return match ($attribute) {
            self::VIEW => $isOwn || $isTeamLeader,
            self::ASSIGN => in_array(Role::SEF->value, $reachable, true) && ($isOwn || $isTeamLeader),
            default => false,
        };
    }

    private function leadsAssigneeTeam(Client $client, User $leader): bool
    {
        foreach ([$client->getPrimaryAccountant(), $client->getBackupAccountant()] as $assignee) {
            if (null === $assignee) {
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
