<?php

namespace App\Service;

/**
 * Filteri matrice "Pregled poslova po klijentima" (SPEC 8r). Sve je u URL-u: q, user, pkg (id-evi sa zarezom), period (YYYY-MM), open, problem.
 * Period je kalendarski mesec roka zadatka.
 */
final class MatrixFilters
{
    /**
     * @param list<int> $packageIds
     */
    public function __construct(
        public readonly string $q = '',
        public readonly ?int $userId = null,
        public readonly array $packageIds = [],
        public readonly int $year = 0,
        public readonly int $month = 0,
        public readonly bool $onlyOpen = false,
        public readonly bool $onlyProblem = false,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query, \DateTimeImmutable $today): self
    {
        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');
        if (preg_match('/^(\d{4})-(\d{1,2})$/', (string) ($query['period'] ?? ''), $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
            $year = (int) $m[1];
            $month = (int) $m[2];
        }
        $pkg = $query['pkg'] ?? [];
        $pkg = \is_array($pkg) ? $pkg : explode(',', (string) $pkg);
        $ids = array_values(array_unique(array_filter(array_map('intval', $pkg), static fn (int $i) => $i > 0)));
        $flag = static fn (string $k): bool => \in_array((string) ($query[$k] ?? ''), ['1', 'on', 'true'], true);

        return new self(
            q: mb_substr(trim((string) ($query['q'] ?? '')), 0, 100),
            userId: (int) ($query['user'] ?? 0) ?: null,
            packageIds: $ids,
            year: $year,
            month: $month,
            onlyOpen: $flag('open'),
            onlyProblem: $flag('problem'),
        );
    }

    public function from(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month));
    }

    public function to(): \DateTimeImmutable
    {
        return $this->from()->modify('last day of this month');
    }

    public function periodKey(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    /** @return array<string, scalar> za linkove, AJAX i izvoz (bez praznih vrednosti) */
    public function toQuery(): array
    {
        return array_filter([
            'q' => '' !== $this->q ? $this->q : null,
            'user' => $this->userId,
            'pkg' => [] !== $this->packageIds ? implode(',', $this->packageIds) : null,
            'period' => $this->periodKey(),
            'open' => $this->onlyOpen ? 1 : null,
            'problem' => $this->onlyProblem ? 1 : null,
        ], static fn ($v) => null !== $v);
    }

    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
