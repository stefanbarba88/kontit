<?php

namespace App\Message;

/**
 * Ponovo pošalji sve buduće događaje korisnika (posle povezivanja Google naloga).
 */
final class SyncUserCalendar
{
    public function __construct(public readonly int $userId)
    {
    }
}
