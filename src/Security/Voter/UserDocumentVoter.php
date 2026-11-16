<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\UserDocument;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Dokumenti korisnika (SPEC 8l):
 * USER_DOCUMENT_VIEW (dokument): vlasnik i admin+; šef samo dokumente vezane za odsustvo člana svog tima (doznake).
 * USER_DOCUMENTS_LIST (korisnik): vlasnik, admin+ i šef za člana svog tima (vidi samo dokumente uz odsustva).
 * USER_DOCUMENT_UPLOAD (korisnik): admin+ za bilo koga, korisnik za sebe.
 * USER_DOCUMENT_DELETE (dokument): vlasnik i admin+ (u korpu). USER_DOCUMENT_PURGE: samo admin+ (trajno).
 */
class UserDocumentVoter extends Voter
{
    public const VIEW = 'USER_DOCUMENT_VIEW';
    public const LIST = 'USER_DOCUMENTS_LIST';
    public const UPLOAD = 'USER_DOCUMENT_UPLOAD';
    public const DELETE = 'USER_DOCUMENT_DELETE';
    public const PURGE = 'USER_DOCUMENT_PURGE';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly UserRepository $users,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::VIEW, self::DELETE, self::PURGE => $subject instanceof UserDocument,
            self::LIST, self::UPLOAD => $subject instanceof User,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $me = $token->getUser();
        if (!$me instanceof User) {
            return false;
        }
        // dokumenti se tokom "Prikaži kao korisnik" samo gledaju: bez otpremanja, brisanja i trajnog brisanja (SPEC 8s)
        if ($token instanceof SwitchUserToken && \in_array($attribute, [self::UPLOAD, self::DELETE, self::PURGE], true)) {
            return false;
        }
        $admin = $this->decisions->decide($token, ['ROLE_ADMIN']);
        $leader = $this->decisions->decide($token, ['ROLE_SEF']);
        $owner = $subject instanceof UserDocument ? $subject->getUser() : $subject;

        return match ($attribute) {
            self::VIEW => $owner === $me || $admin
                || ($leader && null !== $subject->getAbsence() && $owner !== $me && $this->users->isInLeaderScope($me, $owner)),
            self::LIST => $owner === $me || $admin || ($leader && $this->users->isInLeaderScope($me, $owner)),
            self::UPLOAD => $owner === $me || $admin,
            self::DELETE => !$subject->isDeleted() && ($owner === $me || $admin),
            self::PURGE => $admin && $subject->isDeleted(),
            default => false,
        };
    }
}
