<?php

namespace App\Message;

/**
 * Veliki izvoz liste (preko ExportService::LIMIT_SYNC redova) se pravi u pozadini;
 * kad je fajl spreman, korisnik dobija notifikaciju sa linkom.
 */
final class GenerateExport
{
    /**
     * @param array<string, mixed> $query filteri liste u trenutku izvoza
     */
    public function __construct(
        public readonly string $list,
        public readonly string $format,
        public readonly array $query,
        public readonly int $userId,
        public readonly string $token,
    ) {
    }
}
