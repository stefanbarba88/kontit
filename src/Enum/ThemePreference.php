<?php

namespace App\Enum;

enum ThemePreference: string
{
    case LIGHT = 'LIGHT';
    case DARK = 'DARK';
    case SYSTEM = 'SYSTEM';

    public function label(): string
    {
        return match ($this) {
            self::LIGHT => 'Svetla',
            self::DARK => 'Tamna',
            self::SYSTEM => 'Kao sistem',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LIGHT => 'sun',
            self::DARK => 'moon',
            self::SYSTEM => 'monitor',
        };
    }
}
