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

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Po potrebi',
            self::DAILY => 'Dnevno',
            self::WEEKLY => 'Nedeljno',
            self::MONTHLY => 'Mesečno',
            self::QUARTERLY => 'Kvartalno',
            self::YEARLY => 'Godišnje',
        };
    }
}
