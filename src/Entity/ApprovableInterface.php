<?php

namespace App\Entity;

/**
 * Zahtev koji se odobrava, a posle roka se smatra odobrenim (SPEC 8m). Implementira ga UserAbsence;
 * ApprovalService ga obrađuje bez obzira na vrstu, a uključuje se po vrsti zahteva u Podešavanjima.
 */
interface ApprovableInterface
{
    public function isPending(): bool;

    public function getApprovalDeadline(): ?\DateTimeImmutable;

    /**
     * Odobravalac je odobrio.
     */
    public function approve(User $by): void;

    /**
     * Rok je istekao bez odgovora: odobreno automatski (bez korisnika, u istoriji "Sistem").
     */
    public function approveAutomatically(): void;
}
