<?php

namespace App\Enum;

enum AssignmentType: string
{
    case PRIMARY = 'PRIMARY';
    case BACKUP = 'BACKUP';

    public function label(): string
    {
        return match ($this) {
            self::PRIMARY => 'Glavni zadužen',
            self::BACKUP => 'Backup (zamena)',
        };
    }
}
