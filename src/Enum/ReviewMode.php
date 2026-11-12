<?php

namespace App\Enum;

/**
 * Način provere na samom zadatku: AUTO (po poziciji učesnika), NONE (bez provere), SPECIAL (posebna potvrda, proveravači u task_required_approver).
 */
enum ReviewMode: string
{
    case AUTO = 'AUTO';
    case NONE = 'NONE';
    case SPECIAL = 'SPECIAL';

    public function label(): string
    {
        return match ($this) {
            self::AUTO => 'Automatski, po poziciji',
            self::NONE => 'Bez provere',
            self::SPECIAL => 'Posebna potvrda',
        };
    }
}
