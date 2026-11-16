<?php

namespace App\Enum;

/** Režim impersonacije: samo pregled (podrazumevan) ili rad u ime korisnika (SPEC 8s). */
enum ImpersonationMode: string
{
    case VIEW = 'view';
    case EDIT = 'edit';

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'Samo pregled',
            self::EDIT => 'Izmene',
        };
    }
}
