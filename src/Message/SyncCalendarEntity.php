<?php

namespace App\Message;

/**
 * Uskladi Google kalendare za jedan zadatak ili odsustvo (SPEC 8n.3), asinhrono da akcija u aplikaciji ne čeka Google.
 */
final class SyncCalendarEntity
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
    ) {
    }
}
