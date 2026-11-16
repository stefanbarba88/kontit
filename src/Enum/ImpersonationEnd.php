<?php

namespace App\Enum;

/** Razlog završetka impersonacije. */
enum ImpersonationEnd: string
{
    case MANUAL = 'manual';
    case TIMEOUT = 'timeout';
    case LOGOUT = 'logout';
    case STALE = 'stale';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Izlaz iz pregleda',
            self::TIMEOUT => 'Istek posle neaktivnosti',
            self::LOGOUT => 'Odjava',
            self::STALE => 'Istek sesije',
        };
    }
}
