<?php

namespace App\Enum;

enum JobSource: string
{
    case PACKAGE = 'PACKAGE';
    case INDIVIDUAL = 'INDIVIDUAL';

    public function label(): string
    {
        return match ($this) {
            self::PACKAGE => 'Iz paketa',
            self::INDIVIDUAL => 'Pojedinačno',
        };
    }
}
