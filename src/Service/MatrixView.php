<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ServicePackage;
use App\Entity\User;

/** Gotova matrica za prikaz/izvoz: kolone (paketi), redovi (klijenti sa ćelijama) i zbirovi */
final class MatrixView
{
    /**
     * @param list<ServicePackage>                                                                   $columns
     * @param list<array{client: Client, primary: ?User, backup: ?User, cells: array<int, MatrixCell>, other: MatrixCell}> $rows cells su po id-u paketa
     * @param list<User>                                                                             $people zaposleni za filter (glavni i backup vidljivih klijenata)
     * @param list<ServicePackage>                                                                   $allPackages paketi za filter
     */
    public function __construct(
        public readonly MatrixFilters $filters,
        public readonly array $columns,
        public readonly array $rows,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $dangerCells,
        public readonly int $warningCells,
        public readonly int $overdueTasks,
        public readonly array $people,
        public readonly array $allPackages,
    ) {
    }

    public function hasMore(): bool
    {
        return $this->page * $this->perPage < $this->total;
    }
}
