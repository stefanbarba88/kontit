<?php

namespace App\Enum;

/** Stanje ćelije matrice "Pregled poslova po klijentima" (SPEC 8r); računa ga jedino ClientJobMatrixService::cellState() */
enum MatrixState: string
{
    case DANGER = 'danger';
    case SUCCESS = 'success';
    case IN_PROGRESS = 'progress';
    case NONE = 'none';

    /** Tekst za title, aria-label i izvoz */
    public function label(): string
    {
        return match ($this) {
            self::DANGER => 'Kasni',
            self::SUCCESS => 'Urađeno',
            self::IN_PROGRESS => 'U toku',
            self::NONE => 'Nema posla',
        };
    }

    /** Lucide ikonica (boja nije jedini nosilac značenja) */
    public function icon(): string
    {
        return match ($this) {
            self::DANGER => 'triangle-alert',
            self::SUCCESS => 'circle-check-big',
            self::IN_PROGRESS => 'circle',
            self::NONE => 'circle',
        };
    }
}
