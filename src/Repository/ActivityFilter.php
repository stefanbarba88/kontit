<?php

namespace App\Repository;

use App\Enum\ActivityAction;
use Symfony\Component\HttpFoundation\Request;

/**
 * Strana timeline-a sa filterima (SPEC 8f.1): ?page=N&action=CHECKED&period=2026-10-01 do 2026-10-28
 */
final class ActivityFilter
{
    public const PER_PAGE = 20;

    public function __construct(
        public readonly int $page = 1,
        public readonly ?ActivityAction $action = null,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        // flatpickr opseg šalje "Y-m-d do Y-m-d" (ili samo jedan datum)
        preg_match_all('/\d{4}-\d{2}-\d{2}/', (string) $request->query->get('period', ''), $dates);
        $date = static fn (int $i): ?\DateTimeImmutable => isset($dates[0][$i]) ? (\DateTimeImmutable::createFromFormat('!Y-m-d', $dates[0][$i]) ?: null) : null;
        $from = $date(0);
        $to = $date(1) ?? $from;

        return new self(
            max(1, $request->query->getInt('page', 1)),
            ActivityAction::tryFrom((string) $request->query->get('action', '')),
            $from,
            $to,
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * self::PER_PAGE;
    }
}
