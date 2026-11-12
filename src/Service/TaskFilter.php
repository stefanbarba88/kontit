<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\JobTemplate;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;

/**
 * Razrešeni filteri liste zadataka (/tasks, tab zadataka na klijentu i na korisniku). Pravi ga TaskListFilters::resolve(),
 * a jedini upit je TaskRepository::createFilteredQueryBuilder() (lista, brojači i izvoz). Sve je u URL-u (GET parametri).
 *
 * $lockedClient / $lockedAssignee: stranica klijenta ili korisnika; ti filteri se ne mogu menjati iz URL-a i sakriveni su.
 */
final class TaskFilter
{
    public const ORIGINS = ['all', 'auto', 'manual'];
    public const ROLES = ['assigned', 'created', 'both'];

    public function __construct(
        public readonly bool $viewerAdmin,
        public readonly bool $viewerLeader,
        /** 'all' (admin+ i šef: svi koje sme da vidi) ili 'mine' */
        public readonly string $scope = 'mine',
        public readonly string $tab = 'all',
        public readonly ?Client $client = null,
        public readonly ?TaskPriority $priority = null,
        public readonly ?Team $team = null,
        public readonly ?User $assignee = null,
        public readonly ?TaskStatus $status = null,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
        /** upit za naziv, već normalizovan (TextNormalizer) */
        public readonly string $q = '',
        /** izvorni tekst upita, za prikaz u polju */
        public readonly string $qRaw = '',
        public readonly ?JobTemplate $job = null,
        public readonly bool $noJob = false,
        /** 'all' | 'auto' | 'manual' (generatedAutomatically) */
        public readonly string $origin = 'all',
        /** prikaži i završene i otkazane (važi samo za tab "Svi" bez izabranog statusa) */
        public readonly bool $showClosed = false,
        /** samo uz $assignee na stranici korisnika: 'assigned' | 'created' | 'both' */
        public readonly string $role = 'assigned',
        public readonly ?Client $lockedClient = null,
        public readonly ?User $lockedAssignee = null,
        /** Stranica Posebne potvrde: korisnik čije posebne potvrde se prikazuju (tab special_waiting | special_progress | special_done) */
        public readonly ?User $specialApprovalFor = null,
        /** Samo superadmin: sve posebne potvrde, bez obzira na izabranog proveravača */
        public readonly bool $specialAll = false,
    ) {
    }

    /**
     * Kopija sa izmenjenim poljima (za opcije filtera i brojače).
     *
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }

    public function period(): string
    {
        return null === $this->from ? '' : $this->from->format('Y-m-d').($this->to != $this->from ? ' do '.$this->to?->format('Y-m-d') : '');
    }

    /**
     * Broj aktivnih filtera (bez taba i bez zaključanih), za značku na dugmetu filtera.
     */
    public function activeCount(): int
    {
        return \count(array_filter([
            'scope' => $this->viewerLeader && $this->scope !== ($this->viewerAdmin ? 'all' : 'mine'),
            'team' => null !== $this->team,
            'user' => null !== $this->assignee && null === $this->lockedAssignee,
            'client' => null !== $this->client && null === $this->lockedClient,
            'status' => null !== $this->status,
            'priority' => null !== $this->priority,
            'period' => null !== $this->from,
            'q' => '' !== $this->q,
            'job' => null !== $this->job || $this->noJob,
            'origin' => 'all' !== $this->origin,
            'closed' => $this->showClosed,
            'role' => null !== $this->lockedAssignee && 'assigned' !== $this->role,
        ]));
    }

    /**
     * GET parametri za linkove (tabovi, izvoz, paginator); bez zaključanih polja i bez strane.
     *
     * @return array<string, scalar>
     */
    public function toQuery(): array
    {
        $query = [
            'tab' => 'all' !== $this->tab ? $this->tab : null,
            'scope' => $this->viewerLeader && $this->scope !== ($this->viewerAdmin ? 'all' : 'mine') ? $this->scope : null,
            'team' => $this->team?->getId(),
            'user' => null === $this->lockedAssignee ? $this->assignee?->getId() : null,
            'client' => null === $this->lockedClient ? $this->client?->getId() : null,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'period' => '' !== $this->period() ? $this->period() : null,
            'q' => '' !== $this->qRaw ? $this->qRaw : null,
            'job' => $this->noJob ? 'none' : $this->job?->getId(),
            'origin' => 'all' !== $this->origin ? $this->origin : null,
            'closed' => $this->showClosed ? 1 : null,
            'role' => null !== $this->lockedAssignee && 'assigned' !== $this->role ? $this->role : null,
            'all' => $this->specialAll ? 1 : null,
        ];

        return array_filter($query, static fn ($v) => null !== $v);
    }
}
