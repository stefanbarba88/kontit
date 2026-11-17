<?php

namespace App\Export;

use App\Entity\User;
use App\Enum\MatrixState;
use App\Service\ClientJobMatrixService;
use App\Service\MatrixCell;

/**
 * Izvoz matrice "Pregled poslova po klijentima" (SPEC 8r): isti servis i isti filteri kao ekran, pa i ista prava.
 * Kolone prate prikaz: Klijent, Tim (glavni), Backup i po jedna kolona za svaki posao izabrane kategorije;
 * ćelija je status tekstom (Urađeno / Kasni / U toku / Nema posla).
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

    public function getColumns(array $query = [], ?User $user = null): array
    {
        $columns = [
            ['label' => 'Klijent', 'type' => 'text', 'width' => 30],
            ['label' => 'Tim (glavni)', 'type' => 'text', 'width' => 24],
            ['label' => 'Backup', 'type' => 'text', 'width' => 24],
        ];
        $jobs = null === $user ? [] : $this->matrix->build($this->matrix->filters($query), $user, 1, 1)->columns;
        foreach ($jobs as $job) {
            $columns[] = ['label' => (string) $job->getName(), 'type' => 'text', 'width' => 18];
        }

        return $columns;
    }

    public function count(array $query, User $user): int
    {
        return $this->matrix->build($this->matrix->filters($query), $user, 1, 1)->total;
    }

    public function rows(array $query, User $user): iterable
    {
        $view = $this->matrix->build($this->matrix->filters($query), $user, 1, self::ALL);
        foreach ($view->rows as $row) {
            $line = [$row['client']->getShortName(), $row['primary']?->getFullName(), $row['backup']?->getFullName()];
            foreach ($view->columns as $job) {
                $line[] = self::statusText($row['cells'][$job->getId()]);
            }
            yield $line;
        }
    }

    /** Status tekstom za izvoz */
    public static function statusText(MatrixCell $cell): string
    {
        return match ($cell->state) {
            MatrixState::SUCCESS => 'Urađeno',
            MatrixState::DANGER => 'Kasni',
            MatrixState::IN_PROGRESS => 'U toku',
            MatrixState::NONE => 'Nema posla',
        };
    }

    public function describeFilters(array $query, ?User $user = null): string
    {
        $f = $this->matrix->filters($query);
        $parts = ['Period: '.$this->matrix->periodLabel($f)];
        $view = null === $user ? null : $this->matrix->build($f, $user, 1, 1);
        if (null !== $view?->category) {
            $parts[] = 'Kategorija: '.$view->category->getName();
        }
        if ('' !== $f->q) {
            $parts[] = 'Klijent: '.$f->q;
        }
        foreach ($view?->people ?? [] as $person) {
            if ($person->getId() === $f->userId) {
                $parts[] = 'zaposleni: '.$person->getFullName();
            }
        }

        return implode(' · ', $parts);
    }
}
