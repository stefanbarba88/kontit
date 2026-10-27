<?php

namespace App\Enum;

enum TaskPriority: string
{
    case NIZAK = 'NIZAK';
    case NORMALAN = 'NORMALAN';
    case VISOK = 'VISOK';
    case HITAN = 'HITAN';

    public function label(): string
    {
        return match ($this) {
            self::NIZAK => 'Nizak',
            self::NORMALAN => 'Normalan',
            self::VISOK => 'Visok',
            self::HITAN => 'Hitan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NIZAK => 'secondary',
            self::NORMALAN => 'primary',
            self::VISOK => 'warning',
            self::HITAN => 'danger',
        };
    }
}
