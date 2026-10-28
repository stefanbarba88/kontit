<?php

namespace App\Enum;

/**
 * Vrsta neradnog dana (SPEC 8k). Dani u nedelji (vikend) su posebno pravilo: WeeklyNonWorkingRule.
 */
enum NonWorkingDayType: string
{
    case HOLIDAY_RECURRING = 'HOLIDAY_RECURRING';
    case HOLIDAY_ONCE = 'HOLIDAY_ONCE';
    case COLLECTIVE_LEAVE = 'COLLECTIVE_LEAVE';

    public function label(): string
    {
        return match ($this) {
            self::HOLIDAY_RECURRING => 'Praznik, svake godine',
            self::HOLIDAY_ONCE => 'Praznik, ove godine',
            self::COLLECTIVE_LEAVE => 'Kolektivni odmor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HOLIDAY_RECURRING, self::HOLIDAY_ONCE => 'danger',
            self::COLLECTIVE_LEAVE => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HOLIDAY_RECURRING => 'repeat',
            self::HOLIDAY_ONCE => 'calendar-x',
            self::COLLECTIVE_LEAVE => 'palmtree',
        };
    }
}
