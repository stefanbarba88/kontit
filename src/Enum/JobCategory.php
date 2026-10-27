<?php

namespace App\Enum;

enum JobCategory: string
{
    case KNJIZENJE = 'KNJIZENJE';
    case PDV = 'PDV';
    case PLATE = 'PLATE';
    case PRIJAVE = 'PRIJAVE';
    case ZAVRSNI_RACUN = 'ZAVRSNI_RACUN';
    case SAVETOVANJE = 'SAVETOVANJE';
    case ADMINISTRATIVNO = 'ADMINISTRATIVNO';
    case OSTALO = 'OSTALO';

    public function label(): string
    {
        return match ($this) {
            self::KNJIZENJE => 'Knjiženje',
            self::PDV => 'PDV',
            self::PLATE => 'Plate',
            self::PRIJAVE => 'Prijave',
            self::ZAVRSNI_RACUN => 'Završni račun',
            self::SAVETOVANJE => 'Savetovanje',
            self::ADMINISTRATIVNO => 'Administrativno',
            self::OSTALO => 'Ostalo',
        };
    }
}
