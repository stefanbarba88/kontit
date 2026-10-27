<?php

namespace App\Enum;

enum Recurrence: string
{
    case ONE_TIME = 'ONE_TIME';
    case WEEKLY = 'WEEKLY';
    case MONTHLY = 'MONTHLY';
    case QUARTERLY = 'QUARTERLY';
    case YEARLY = 'YEARLY';

    public function label(): string
    {
        return match ($this) {
            self::ONE_TIME => 'Jednokratno',
            self::WEEKLY => 'Nedeljno',
            self::MONTHLY => 'Mesečno',
            self::QUARTERLY => 'Kvartalno',
            self::YEARLY => 'Godišnje',
        };
    }

    /**
     * Koliko puta godišnje se posao ponavlja (za prihod i opterećenje na mesečnom nivou).
     */
    public function perMonth(): float
    {
        return match ($this) {
            self::ONE_TIME => 0.0,
            self::WEEKLY => 52 / 12,
            self::MONTHLY => 1.0,
            self::QUARTERLY => 1 / 3,
            self::YEARLY => 1 / 12,
        };
    }
}
