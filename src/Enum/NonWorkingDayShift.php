<?php

namespace App\Enum;

/**
 * Šta sa rokom koji padne na neradni dan (SPEC 8g.3).
 */
enum NonWorkingDayShift: string
{
    case NEXT = 'NEXT';
    case PREVIOUS = 'PREVIOUS';
    /** Bez pomeranja: rok ostaje tačno tog dana, čak i ako je vikend ili praznik. */
    case EXACT = 'EXACT';

    public function label(): string
    {
        return match ($this) {
            self::NEXT => 'Prvi sledeći radni dan',
            self::PREVIOUS => 'Prethodni radni dan',
            self::EXACT => 'Tačno taj dan (i ako je neradni)',
        };
    }
}
