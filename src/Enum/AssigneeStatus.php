<?php

namespace App\Enum;

enum AssigneeStatus: string
{
    case NOVO = 'NOVO';
    case U_RADU = 'U_RADU';
    case ZAVRSIO = 'ZAVRSIO';

    public function label(): string
    {
        return match ($this) {
            self::NOVO => 'Novo',
            self::U_RADU => 'U radu',
            self::ZAVRSIO => 'Završio',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NOVO => 'secondary',
            self::U_RADU => 'primary',
            self::ZAVRSIO => 'success',
        };
    }
}
