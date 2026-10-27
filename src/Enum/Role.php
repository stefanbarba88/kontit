<?php

namespace App\Enum;

enum Role: string
{
    case SUPERADMIN = 'ROLE_SUPERADMIN';
    case ADMIN = 'ROLE_ADMIN';
    case SEF = 'ROLE_SEF';
    case RADNIK = 'ROLE_RADNIK';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Superadmin',
            self::ADMIN => 'Admin',
            self::SEF => 'Šef',
            self::RADNIK => 'Radnik',
        };
    }
}
