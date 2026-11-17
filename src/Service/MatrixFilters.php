<?php

namespace App\Service;

/**
 * Filteri matrice "Pregled poslova po klijentima" (SPEC 8r). Sve je u URL-u: q, user, cat (id kategorije poslova), period (YYYY-MM), open, problem.
 * Period je kalendarski mesec roka zadatka.
 */
final class MatrixFilters
{
    /**
     * @param ?int $categoryId kategorija poslova (JobCategory); null = prva po redosledu (razrešava servis)
     */
    public function __construct(
        public readonly string $q = '',
        public readonly ?int $userId = null,
        public readonly ?int $categoryId = null,
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
        // neispravna vrednost (prazno, tekst, niz) se ignoriše
        $cat = $query['cat'] ?? null;
        $cat = \is_scalar($cat) && ctype_digit((string) $cat) && (int) $cat > 0 ? (int) $cat : null;
        $flag = static fn (string $k): bool => \in_array((string) ($query[$k] ?? ''), ['1', 'on', 'true'], true);

        return new self(
            q: mb_substr(trim((string) ($query['q'] ?? '')), 0, 100),
            userId: \is_scalar($query['user'] ?? null) && ctype_digit((string) $query['user']) && (int) $query['user'] > 0 ? (int) $query['user'] : null,
            categoryId: $cat,
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
            'cat' => $this->categoryId,
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
