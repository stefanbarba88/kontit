<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\JobCategory;
use App\Entity\JobTemplate;
use App\Entity\User;

/** Gotova matrica za prikaz/izvoz: kolone (poslovi izabrane kategorije), redovi (klijenti sa ćelijama) i zbirovi */
final class MatrixView
{
    /**
     * @param list<JobTemplate>                                                                      $columns
     * @param list<array{client: Client, primary: ?User, backup: ?User, cells: array<int, MatrixCell>}> $rows cells su po id-u posla
     * @param list<User>                                                                             $people zaposleni za filter (glavni i backup vidljivih klijenata)
     * @param list<JobCategory>                                                                      $categories aktivne kategorije poslova za filter
     */
    public function __construct(
        public readonly MatrixFilters $filters,
        public readonly array $columns,
        public readonly array $rows,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $people,
        public readonly array $categories,
        public readonly ?JobCategory $category,
    ) {
    }

    /** Izabrana kategorija nema nijedan posao koji neki vidljiv klijent ima (ili uopšte nema kategorija) */
    public function categoryIsEmpty(): bool
    {
        return [] === $this->columns;
    }

    public function hasMore(): bool
    {
        return $this->page * $this->perPage < $this->total;
    }
}
