<?php

namespace App\Enum;

enum LegalForm: string
{
    case DOO = 'DOO';
    case AD = 'AD';
    case PREDUZETNIK = 'PREDUZETNIK';
    case UDRUZENJE = 'UDRUZENJE';
    case OSTALO = 'OSTALO';

    public function label(): string
    {
        return match ($this) {
            self::DOO => 'D.O.O.',
            self::AD => 'A.D.',
            self::PREDUZETNIK => 'Preduzetnik',
            self::UDRUZENJE => 'Udruženje',
            self::OSTALO => 'Ostalo',
        };
    }
}
