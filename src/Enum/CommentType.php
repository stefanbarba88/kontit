<?php

namespace App\Enum;

enum CommentType: string
{
    case KOMENTAR = 'KOMENTAR';
    case VRACANJE_NA_DORADU = 'VRACANJE_NA_DORADU';

    public function label(): string
    {
        return match ($this) {
            self::KOMENTAR => 'Komentar',
            self::VRACANJE_NA_DORADU => 'Vraćeno na doradu',
        };
    }
}
