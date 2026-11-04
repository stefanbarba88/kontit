<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\UserAbsence;
use App\Enum\AbsenceStatus;
use App\Service\AbsenceService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * ABSENCE_CREATE (bez subjekta): svako prijavljen (za koga sme, proverava AbsenceService).
 * ABSENCE_DECIDE: odsustvo je na odobrenju, a odobrava admin+ ili šef tima radnika (nikad sam sebi).
 * ABSENCE_EDIT: vrsta, datumi i napomena (pravila u AbsenceService::canEdit).
 * ABSENCE_CANCEL: podnosilac (ili onaj ko je upisao) dok je na odobrenju, admin+ u bilo kom trenutku.
 */
class AbsenceVoter extends Voter
{
    public const CREATE = 'ABSENCE_CREATE';
    public const DECIDE = 'ABSENCE_DECIDE';
    public const EDIT = 'ABSENCE_EDIT';
    public const CANCEL = 'ABSENCE_CANCEL';
    /** Admin poništava automatsko odobrenje (SPEC 8m) */
    public const REVOKE = 'ABSENCE_REVOKE';

    public function __construct(private readonly AbsenceService $service)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE => null === $subject,
            self::DECIDE, self::EDIT, self::CANCEL, self::REVOKE => $subject instanceof UserAbsence,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if (self::CREATE === $attribute) {
            return true;
        }

        /** @var UserAbsence $subject */
        $status = $subject->getStatus();

        return match ($attribute) {
            self::EDIT => $this->service->canEdit($user, $subject),
            self::DECIDE => AbsenceStatus::NA_ODOBRENJU === $status && $this->service->canDecide($user, $subject->getUser()),
            // podnosilac dok je zahtev na odobrenju, admin u bilo kom trenutku (SPEC 8j.2)
            self::CANCEL => \in_array($status, AbsenceStatus::blocking(), true)
                && ((AbsenceStatus::NA_ODOBRENJU === $status && ($subject->getUser() === $user || $subject->getCreatedBy() === $user))
                    || \in_array('ROLE_ADMIN', $token->getRoleNames(), true) || \in_array('ROLE_SUPERADMIN', $token->getRoleNames(), true)),
            self::REVOKE => AbsenceStatus::AUTOMATSKI_ODOBRENO === $status
                && (\in_array('ROLE_ADMIN', $token->getRoleNames(), true) || \in_array('ROLE_SUPERADMIN', $token->getRoleNames(), true)),
            default => false,
        };
    }
}
