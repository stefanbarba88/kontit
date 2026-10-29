<?php

namespace App\Enum;

/**
 * Uputstva za korisnike (SPEC 8o). Sadržaj je u docs/uputstvo/{value}.md.
 * Vidi ih svako ko ima rolu uputstva (role_hierarchy): radnik svoje, šef šefovo i radničko, admin i superadmin sva.
 */
enum HelpGuide: string
{
    case ADMIN = 'admin';
    case SEF = 'sef';
    case RADNIK = 'radnik';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::SEF => 'Šef',
            self::RADNIK => 'Radnik',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::ADMIN => 'Uputstvo za admina',
            self::SEF => 'Uputstvo za šefa',
            self::RADNIK => 'Uputstvo za radnika',
        };
    }

    public function role(): Role
    {
        return match ($this) {
            self::ADMIN => Role::ADMIN,
            self::SEF => Role::SEF,
            self::RADNIK => Role::RADNIK,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ADMIN => 'shield-check',
            self::SEF => 'users',
            self::RADNIK => 'user',
        };
    }
}
