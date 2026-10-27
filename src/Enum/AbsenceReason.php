<?php

namespace App\Enum;

enum AbsenceReason: string
{
    case GODISNJI = 'GODISNJI';
    case BOLOVANJE = 'BOLOVANJE';
    case OSTALO = 'OSTALO';

    public function label(): string
    {
        return match ($this) {
            self::GODISNJI => 'Godišnji odmor',
            self::BOLOVANJE => 'Bolovanje',
            self::OSTALO => 'Ostalo',
        };
    }
}
