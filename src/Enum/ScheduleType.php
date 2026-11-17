<?php

namespace App\Enum;

/**
 * Tip rasporeda posla (SPEC 8g.3). NONE ("Po potrebi"): sistem ne pravi zadatke sam.
 */
enum ScheduleType: string
{
    case NONE = 'NONE';
    case DAILY = 'DAILY';
    case WEEKLY = 'WEEKLY';
    case MONTHLY = 'MONTHLY';
    case QUARTERLY = 'QUARTERLY';
    case YEARLY = 'YEARLY';
    /** N-ti radni dan u mesecu (nije N-ti dan u mesecu); vrednost staje u VARCHAR(12) */
    case NTH_WORKDAY = 'NTH_WORKDAY';
    /** N-ti radni dan od kraja meseca (1 = poslednji radni dan); vrednost staje u VARCHAR(12) */
    case WORKDAY_END = 'WORKDAY_END';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Po potrebi',
            self::DAILY => 'Dnevno',
            self::WEEKLY => 'Nedeljno',
            self::MONTHLY => 'Mesečno',
            self::QUARTERLY => 'Kvartalno',
            self::YEARLY => 'Godišnje',
            self::NTH_WORKDAY => 'N-ti radni dan',
            self::WORKDAY_END => 'Radni dan od kraja',
        };
    }
}
