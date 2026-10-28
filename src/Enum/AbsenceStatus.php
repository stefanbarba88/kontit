<?php

namespace App\Enum;

/**
 * Status zahteva za odsustvo (SPEC 8j.2): NA_ODOBRENJU -> ODOBRENO / ODBIJENO / AUTOMATSKI_ODOBRENO (8m), plus OTKAZANO.
 */
enum AbsenceStatus: string
{
    case NA_ODOBRENJU = 'NA_ODOBRENJU';
    case ODOBRENO = 'ODOBRENO';
    case AUTOMATSKI_ODOBRENO = 'AUTOMATSKI_ODOBRENO';
    case ODBIJENO = 'ODBIJENO';
    case OTKAZANO = 'OTKAZANO';

    public function label(): string
    {
        return match ($this) {
            self::NA_ODOBRENJU => 'Na odobrenju',
            self::ODOBRENO => 'Odobreno',
            self::AUTOMATSKI_ODOBRENO => 'Automatski odobreno',
            self::ODBIJENO => 'Odbijeno',
            self::OTKAZANO => 'Otkazano',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NA_ODOBRENJU => 'warning',
            self::ODOBRENO => 'success',
            self::AUTOMATSKI_ODOBRENO => 'info',
            self::ODBIJENO => 'danger',
            self::OTKAZANO => 'secondary',
        };
    }

    /**
     * Odsustvo je na snazi (pokreće zamenu, troši dane, prikazuje se u kalendaru).
     */
    public function isApproved(): bool
    {
        return self::ODOBRENO === $this || self::AUTOMATSKI_ODOBRENO === $this;
    }

    /**
     * @return list<self>
     */
    public static function approved(): array
    {
        return [self::ODOBRENO, self::AUTOMATSKI_ODOBRENO];
    }

    /**
     * Statusi koji zauzimaju period (novo odsustvo se ne sme preklopiti sa njima).
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::NA_ODOBRENJU, self::ODOBRENO, self::AUTOMATSKI_ODOBRENO];
    }
}
