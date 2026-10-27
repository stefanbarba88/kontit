<?php

namespace App\Enum;

enum AbsenceStatus: string
{
    case NA_CEKANJU = 'NA_CEKANJU';
    case ODOBRENO = 'ODOBRENO';
    case ODBIJENO = 'ODBIJENO';
    case OTKAZANO = 'OTKAZANO';

    public function label(): string
    {
        return match ($this) {
            self::NA_CEKANJU => 'Čeka odobrenje',
            self::ODOBRENO => 'Odobreno',
            self::ODBIJENO => 'Odbijeno',
            self::OTKAZANO => 'Otkazano',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NA_CEKANJU => 'warning',
            self::ODOBRENO => 'success',
            self::ODBIJENO => 'danger',
            self::OTKAZANO => 'secondary',
        };
    }
}
