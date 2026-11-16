<?php

namespace App\Service;

use App\Enum\MatrixState;

/** Jedna ćelija matrice: brojevi, stanje i kratak tekst za tooltip */
final class MatrixCell
{
    public function __construct(
        public readonly int $total,
        public readonly int $done,
        public readonly int $overdue,
        public readonly int $soon,
        public readonly MatrixState $state,
        public readonly string $summary,
    ) {
    }

    public function open(): int
    {
        return $this->total - $this->done;
    }

    /** za progres liniju, 0–100 */
    public function percent(): int
    {
        return $this->total > 0 ? (int) round($this->done * 100 / $this->total) : 0;
    }
}
