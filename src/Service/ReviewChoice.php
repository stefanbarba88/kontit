<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\ReviewMode;

/**
 * Izbor načina provere iz forme zadatka (polje "Provera"). Server ga uvek proverava prema TaskReviewPolicy::modesFor().
 */
final class ReviewChoice
{
    /**
     * @param list<User> $approvers proveravači za SPECIAL (aktivni admini i superadmini)
     */
    public function __construct(
        public readonly ReviewMode $mode,
        public readonly array $approvers = [],
    ) {
    }
}
