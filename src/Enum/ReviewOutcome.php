<?php

namespace App\Enum;

/**
 * Ishod odluke TaskReviewPolicy kad zadatak ispuni uslov za završetak.
 */
enum ReviewOutcome: string
{
    /** Posebna potvrda: potvrđuju samo proveravači sa zadatka (snapshot) */
    case SPECIAL = 'SPECIAL';
    /** Provera po poziciji: šef tima učesnika ili admin */
    case BY_POSITION = 'BY_POSITION';
    /** Bez provere: zadatak odmah ide u ZAVRSENO */
    case NONE = 'NONE';
}
