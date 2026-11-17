<?php

namespace App\Export;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Izvoz liste korisnika (/admin/users): isti upit kao UserController::index, samo uz USER_MANAGE.
 * Interna napomena se ne izvozi.
 */
final class UserExport implements ExportListInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Security $security,
        private readonly \Doctrine\ORM\EntityManagerInterface $em,
    ) {
    }

    public function getName(): string
    {
        return 'korisnici';
    }

    public function getTitle(): string
    {
        return 'Korisnici';
    }

    public function isAllowed(User $user): bool
    {
        return $this->security->isGrantedForUser($user, 'USER_MANAGE');
    }

    public function getColumns(array $query = [], ?User $user = null): array
    {
        return [
            ['label' => 'Ime i prezime', 'type' => 'text', 'width' => 26],
            ['label' => 'Email', 'type' => 'text', 'width' => 30],
            ['label' => 'Prava pristupa', 'type' => 'text', 'width' => 14],
            ['label' => 'Pozicija', 'type' => 'text', 'width' => 22],
            ['label' => 'Telefon', 'type' => 'text', 'width' => 16],
            ['label' => 'Status', 'type' => 'text', 'width' => 10],
            ['label' => 'Poslednja prijava', 'type' => 'date', 'width' => 16],
        ];
    }

    public function count(array $query, User $user): int
    {
        return (int) (clone $this->queryBuilder($query))->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
    }

    public function rows(array $query, User $user): iterable
    {
        foreach ($this->queryBuilder($query)->getQuery()->getResult() as $u) {
            yield [
                $u->getFullName(),
                $u->getEmail(),
                $u->getHighestRole()->label(),
                $u->getPosition()?->getName(),
                $u->getPhone(),
                $u->isActive() ? 'aktivan' : 'neaktivan',
                $u->getLastLoginAt(),
            ];
        }
    }

    public function describeFilters(array $query, ?User $user = null): string
    {
        $parts = [];
        if ('' !== (string) ($query['q'] ?? '')) {
            $parts[] = 'Pretraga: '.$query['q'];
        }
        if ($role = Role::tryFrom((string) ($query['role'] ?? ''))) {
            $parts[] = 'Prava pristupa: '.$role->label();
        }
        if ((int) ($query['position'] ?? 0) > 0 && ($position = $this->em->find(\App\Entity\Position::class, (int) $query['position']))) {
            $parts[] = 'Pozicija: '.$position->getName();
        }
        if (\in_array($query['status'] ?? '', ['active', 'inactive'], true)) {
            $parts[] = 'Status: '.('active' === $query['status'] ? 'aktivni' : 'neaktivni');
        }

        return $parts ? implode(' · ', $parts) : 'Svi korisnici';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function queryBuilder(array $query): QueryBuilder
    {
        return $this->users->createListQueryBuilder(
            (string) ($query['q'] ?? ''),
            (string) ($query['role'] ?? ''),
            (string) ($query['status'] ?? ''),
            (int) ($query['position'] ?? 0),
        );
    }
}
