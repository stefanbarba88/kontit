<?php

namespace App\Enum;

/** Ponavljanje stavke u "Podsetniku" */
enum TodoRepeat: string
{
    case NONE = 'NONE';
    case DAILY = 'DAILY';
    case WORKDAYS = 'WORKDAYS';
    case WEEKLY = 'WEEKLY';
    case MONTHLY = 'MONTHLY';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Ne ponavlja se',
            self::DAILY => 'Svaki dan',
            self::WORKDAYS => 'Svaki radni dan',
            self::WEEKLY => 'Svake nedelje',
            self::MONTHLY => 'Svakog meseca',
        };
    }
}
