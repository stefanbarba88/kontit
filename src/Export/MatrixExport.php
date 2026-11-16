<?php

namespace App\Export;

use App\Entity\User;
use App\Service\ClientJobMatrixService;
use App\Service\MatrixCell;

/**
 * Izvoz matrice "Pregled poslova po klijentima" (SPEC 8r): isti servis i isti filteri kao ekran, pa i ista prava.
 * Kolone su fiksne (getColumns nema upit), zato je izvoz u dugom formatu: red po klijentu i paketu, plus red "Ostali taskovi".
 * Ćelije bez zadataka u periodu se ne izvoze.
 */
final class MatrixExport implements ExportListInterface
{
    private const ALL = 100000;

    public function __construct(private readonly ClientJobMatrixService $matrix)
    {
    }

    public function getName(): string
    {
        return 'pregled';
    }

    public function getTitle(): string
    {
        return 'Pregled poslova po klijentima';
    }

    public function isAllowed(User $user): bool
    {
        return true;
    }

    public function getColumns(): array
    {
        return [
            ['label' => 'Klijent', 'type' => 'text', 'width' => 30],
            ['label' => 'Zaduženi', 'type' => 'text', 'width' => 24],
            ['label' => 'Backup', 'type' => 'text', 'width' => 24],
            ['label' => 'Paket', 'type' => 'text', 'width' => 26],
            ['label' => 'Period', 'type' => 'text', 'width' => 16],
            ['label' => 'Ukupno', 'type' => 'number', 'width' => 10],
            ['label' => 'Gotovo', 'type' => 'number', 'width' => 10],
            ['label' => 'Kasni', 'type' => 'number', 'width' => 10],
            ['label' => 'Ističe uskoro', 'type' => 'number', 'width' => 14],
            ['label' => 'Stanje', 'type' => 'text', 'width' => 16],
        ];
    }

    public function count(array $query, User $user): int
    {
        return $this->matrix->build($this->matrix->filters($query), $user, 1, 1)->total;
    }

    public function rows(array $query, User $user): iterable
    {
        $f = $this->matrix->filters($query);
        $view = $this->matrix->build($f, $user, 1, self::ALL);
        $period = $this->matrix->periodLabel($f);
        foreach ($view->rows as $row) {
            $cells = [];
            foreach ($view->columns as $package) {
                $cells[$package->getName()] = $row['cells'][$package->getId()];
            }
            $cells['Ostali taskovi'] = $row['other'];
            foreach ($cells as $name => $cell) {
                if (!$cell instanceof MatrixCell || 0 === $cell->total) {
                    continue;
                }
                yield [
                    $row['client']->getShortName(),
                    $row['primary']?->getFullName(),
                    $row['backup']?->getFullName(),
                    $name,
                    $period,
                    $cell->total,
                    $cell->done,
                    $cell->overdue,
                    $cell->soon,
                    $cell->state->label(),
                ];
            }
        }
    }

    public function describeFilters(array $query, ?User $user = null): string
    {
        $f = $this->matrix->filters($query);
        $parts = ['Period: '.$this->matrix->periodLabel($f)];
        if ('' !== $f->q) {
            $parts[] = 'Klijent: '.$f->q;
        }
        if ($f->onlyOpen) {
            $parts[] = 'samo nezavršeno';
        }
        if ($f->onlyProblem) {
            $parts[] = 'samo problematični';
        }

        return implode(' · ', $parts);
    }
}
