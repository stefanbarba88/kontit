<?php

namespace App\Enum;

enum JobSource: string
{
    case PACKAGE = 'PACKAGE';
    case AD_HOC = 'AD_HOC';

    public function label(): string
    {
        return match ($this) {
            self::PACKAGE => 'Iz paketa',
            self::AD_HOC => 'Ad hoc',
        };
    }
}
