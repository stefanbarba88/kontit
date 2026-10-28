<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\Role;

/**
 * Pretvara vrednosti iz Doctrine changeset-a u tekst za ActivityLog (čuva se prikazni oblik,
 * jer istorija mora ostati čitljiva i kad se enum, naziv ili korisnik kasnije promene).
 */
class ActivityLogger
{
    private const EXCERPT_LENGTH = 200;

    public function format(mixed $value): ?string
    {
        return match (true) {
            null === $value, '' === $value => null,
            $value instanceof \BackedEnum && method_exists($value, 'label') => $value->label(),
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => '00:00' === $value->format('H:i') ? $value->format('d.m.Y.') : $value->format('d.m.Y. H:i'),
            \is_bool($value) => $value ? 'Da' : 'Ne',
            $value instanceof User => $value->getFullName(),
            $value instanceof Client => (string) $value->getShortName(),
            \is_string($value) => $this->excerpt($value),
            \is_scalar($value) => (string) $value,
            default => null,
        };
    }

    public function excerpt(string $text, int $length = self::EXCERPT_LENGTH): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }

    /**
     * Najviša rola iz JSON niza rola korisnika (radnik ako je niz prazan).
     *
     * @param list<string> $roles
     */
    public function roleLabel(array $roles): string
    {
        foreach ([Role::SUPERADMIN, Role::ADMIN, Role::SEF, Role::RADNIK] as $role) {
            if (\in_array($role->value, $roles, true)) {
                return $role->label();
            }
        }

        return Role::RADNIK->label();
    }

    /**
     * Pretvara changeset u [naziv polja => [staro, novo]] za zadata polja i izbacuje ona gde se prikaz nije promenio
     * (npr. isti datum u novoj instanci).
     *
     * Doctrine u changeset-u za enum kolone vraća sirovu vrednost (string), pa se enum klasa polja zadaje u $enums.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet  Doctrine changeset
     * @param array<string, string>                    $labels     svojstvo => naziv u istoriji
     * @param array<string, class-string<\BackedEnum>> $enums     svojstvo => enum klasa
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function diff(array $changeSet, array $labels, array $enums = []): array
    {
        $changes = [];
        foreach ($labels as $field => $label) {
            if (!isset($changeSet[$field])) {
                continue;
            }
            [$old, $new] = $changeSet[$field];
            if (isset($enums[$field])) {
                $old = \is_string($old) || \is_int($old) ? $enums[$field]::tryFrom($old) : $old;
                $new = \is_string($new) || \is_int($new) ? $enums[$field]::tryFrom($new) : $new;
            }
            $old = $this->format($old);
            $new = $this->format($new);
            if ($old !== $new) {
                $changes[$label] = [$old, $new];
            }
        }

        return $changes;
    }
}
