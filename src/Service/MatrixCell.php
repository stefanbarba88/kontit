<?php

namespace App\Service;

use App\Enum\MatrixState;

/** Jedna ćelija matrice: brojevi (samo za račun stanja) i stanje */
final class MatrixCell
{
    public function __construct(
        public readonly int $total,
        public readonly int $done,
        public readonly int $overdue,
        public readonly MatrixState $state,
    ) {
    }
}
