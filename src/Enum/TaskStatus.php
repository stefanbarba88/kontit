<?php

namespace App\Enum;

enum TaskStatus: string
{
    case NOVO = 'NOVO';
    case U_RADU = 'U_RADU';
    case CEKA_KLIJENTA = 'CEKA_KLIJENTA';
    case NA_PROVERI = 'NA_PROVERI';
    case ZAVRSENO = 'ZAVRSENO';
    case OTKAZANO = 'OTKAZANO';

    public function label(): string
    {
        return match ($this) {
            self::NOVO => 'Novo',
            self::U_RADU => 'U radu',
            self::CEKA_KLIJENTA => 'Čeka klijenta',
            self::NA_PROVERI => 'Na proveri',
            self::ZAVRSENO => 'Završeno',
            self::OTKAZANO => 'Otkazano',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NOVO => 'secondary',
            self::U_RADU => 'primary',
            self::CEKA_KLIJENTA => 'warning',
            self::NA_PROVERI => 'purple',
            self::ZAVRSENO => 'success',
            self::OTKAZANO => 'danger',
        };
    }

    /**
     * Zadatak u kome se još radi (učesnici mogu da završavaju svoj deo).
     */
    public function isWorkable(): bool
    {
        return in_array($this, [self::NOVO, self::U_RADU, self::CEKA_KLIJENTA], true);
    }

    /**
     * Nije završen ni otkazan.
     */
    public function isOpen(): bool
    {
        return !in_array($this, [self::ZAVRSENO, self::OTKAZANO], true);
    }
}
