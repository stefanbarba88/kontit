<?php

namespace App\Export;

use App\Entity\User;
use App\Repository\AbsenceTypeRepository;
use App\Service\AbsenceOverview;
use App\Service\TaskService;

/**
 * Izvoz evidencije odsustava (SPEC 8j.3, tab "Evidencija"): zbir radnih dana po radniku i vrsti za godinu i stanje godišnjeg.
 * Isti opseg kao na ekranu (šef svoj tim, admin svi, filter po timu).
 */
class AbsenceRecordExport implements ExportListInterface
{
    public function __construct(
        private readonly AbsenceOverview $overview,
        private readonly AbsenceTypeRepository $types,
        private readonly TaskService $tasks,
    ) {
    }

    public function getName(): string
    {
        return 'odsustva';
    }

    public function getTitle(): string
    {
        return 'Evidencija odsustava';
    }

    public function isAllowed(User $user): bool
    {
        return $this->tasks->isLeader($user);
    }

    public function getColumns(): array
    {
        $columns = [['label' => 'Radnik', 'type' => 'text', 'width' => 26]];
        foreach ($this->types->findOrdered() as $type) {
            $columns[] = ['label' => (string) $type->getName(), 'type' => 'number', 'width' => 12];
        }

        return array_merge($columns, [
            ['label' => 'Ukupno', 'type' => 'number', 'width' => 10],
            ['label' => 'Godišnji: pravo', 'type' => 'number', 'width' => 14],
            ['label' => 'Preneto', 'type' => 'number', 'width' => 10],
            ['label' => 'Iskorišćeno', 'type' => 'number', 'width' => 12],
            ['label' => 'Preostalo', 'type' => 'number', 'width' => 11],
        ]);
    }

    public function count(array $query, User $user): int
    {
        return \count($this->overview->usersFor($user, $this->team($query)));
    }

    public function rows(array $query, User $user): iterable
    {
        $records = $this->overview->records($user, $this->year($query), $this->team($query));
        foreach ($records['rows'] as $row) {
            $line = [$row['user']->getFullName()];
            foreach ($records['types'] as $type) {
                $line[] = $row['byType'][$type->getId()] ?? 0;
            }
            yield array_merge($line, [$row['total'], $row['leave']['entitled'], $row['leave']['carriedOver'], $row['leave']['used'], $row['leave']['remaining']]);
        }
    }

    public function describeFilters(array $query, ?User $user = null): string
    {
        return sprintf('Godina: %d', $this->year($query));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function year(array $query): int
    {
        return max(2000, min(2100, (int) ($query['year'] ?? date('Y')) ?: (int) date('Y')));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function team(array $query): ?int
    {
        return (int) ($query['team'] ?? 0) ?: null;
    }
}
