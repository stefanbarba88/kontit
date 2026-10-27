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
 * ABSENCE_DECIDE: odsustvo čeka odobrenje, a odobrava admin+ ili šef tima radnika (nikad sam sebi).
 * ABSENCE_CANCEL: odsustvo čeka ili je odobreno i nije prošlo, a otkazuje radnik, onaj ko ga je upisao, admin+ ili šef tima.
 */
class AbsenceVoter extends Voter
{
    public const CREATE = 'ABSENCE_CREATE';
    public const DECIDE = 'ABSENCE_DECIDE';
    public const CANCEL = 'ABSENCE_CANCEL';

    public function __construct(private readonly AbsenceService $service)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE => null === $subject,
            self::DECIDE, self::CANCEL => $subject instanceof UserAbsence,
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
            self::DECIDE => AbsenceStatus::NA_CEKANJU === $status && !$subject->hasEnded()
                && $this->service->canDecide($user, $subject->getUser()),
            self::CANCEL => in_array($status, [AbsenceStatus::NA_CEKANJU, AbsenceStatus::ODOBRENO], true) && !$subject->hasEnded()
                && ($subject->getUser() === $user || $subject->getCreatedBy() === $user || $this->service->canDecide($user, $subject->getUser())),
            default => false,
        };
    }
}
