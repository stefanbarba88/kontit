<?php

namespace App\Export;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Lista koja se može izvesti (SPEC 8e). Koristi isti upit i iste Votere kao lista na ekranu,
 * a filteri dolaze iz istog query stringa.
 */
#[AutoconfigureTag('app.export_list')]
interface ExportListInterface
{
    /** Ključ u ruti i nazivu fajla, npr. "klijenti". */
    public function getName(): string;

    public function getTitle(): string;

    public function isAllowed(User $user): bool;

    /**
     * Kolone: [['label' => 'Naziv', 'type' => 'text|number|money|date', 'width' => 30], ...]
     *
     * Query i korisnik su tu zbog lista čije kolone zavise od filtera i vidljivosti (matrica: kolone izabrane kategorije).
     *
     * @param array<string, mixed> $query
     *
     * @return list<array{label: string, type: string, width: int}>
     */
    public function getColumns(array $query = [], ?User $user = null): array;

    /**
     * Broj redova pre provere prava po redu (za odluku da li izvoz ide u pozadini).
     *
     * @param array<string, mixed> $query
     */
    public function count(array $query, User $user): int;

    /**
     * Redovi koje korisnik sme da vidi, kao nizovi vrednosti po kolonama (datumi kao \DateTimeInterface).
     *
     * @param array<string, mixed> $query
     *
     * @return iterable<list<mixed>>
     */
    public function rows(array $query, User $user): iterable;

    /**
     * Opis primenjenih filtera za zaglavlje PDF-a, npr. "Status: aktivni".
     *
     * @param array<string, mixed> $query
     */
    public function describeFilters(array $query, ?User $user = null): string;
}
