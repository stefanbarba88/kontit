<?php

namespace App\Enum;

/** Stanje ćelije matrice "Pregled poslova po klijentima" (SPEC 8r); računa ga jedino ClientJobMatrixService::cellState() */
enum MatrixState: string
{
    case DANGER = 'danger';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case IN_PROGRESS = 'progress';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::DANGER => 'Kasni',
            self::SUCCESS => 'Sve gotovo',
            self::WARNING => 'Ističe uskoro',
            self::IN_PROGRESS => 'U toku',
            self::NONE => 'Nema posla',
        };
    }

    /** Lucide ikonica (boja nije jedini nosilac značenja) */
    public function icon(): ?string
    {
        return match ($this) {
            self::DANGER => 'triangle-alert',
            self::SUCCESS => 'circle-check-big',
            self::WARNING => 'clock-alert',
            self::IN_PROGRESS => 'circle-ellipsis',
            default => null,
        };
    }

    public function isProblem(): bool
    {
        return \in_array($this, [self::DANGER, self::WARNING], true);
    }
}
