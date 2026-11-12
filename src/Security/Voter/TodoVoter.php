<?php

namespace App\Security\Voter;

use App\Entity\TodoItem;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * TODO_VIEW / TODO_EDIT / TODO_DELETE: samo vlasnik stavke. NEMA role hijerarhije: ni admin ni superadmin ne prolaze.
 * Kontroleri dodatno učitavaju stavku kroz TodoItemRepository::findOwned, pa tuđa stavka vraća 404 (ne 403).
 */
class TodoVoter extends Voter
{
    public const VIEW = 'TODO_VIEW';
    public const EDIT = 'TODO_EDIT';
    public const DELETE = 'TODO_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true) && $subject instanceof TodoItem;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject instanceof TodoItem && $subject->getOwner()->getId() === $user->getId();
    }
}
