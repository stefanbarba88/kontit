<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\Role;

/**
 * Opšte pravilo vidljivosti (stavka 6): korisnik vidi detalje (odsustva, dokumente, zadatke, klijente, statistiku)
 * samo za sebe i za korisnike ispod svoje role u svom opsegu. Šef zato u timu vidi samo radnike (i sebe),
 * a ne i člana tima koji je šef ili admin. Admin i superadmin vide celu firmu.
 */
final class TeamScope
{
    /**
     * Da li $target spada u opseg šefa $leader po roli: on sam ili korisnik na nivou radnika.
     */
    public static function covers(User $leader, User $target): bool
    {
        return $leader === $target || Role::RADNIK === $target->getHighestRole();
    }

    /**
     * Uslov (DQL i SQL): korisnik $alias je na nivou radnika, bez role šef, admin ili superadmin.
     * Role se čuvaju kao JSON niz, pa se traži naziv role u tekstu.
     */
    public static function worker(string $alias): string
    {
        return sprintf("(%1\$s.roles NOT LIKE '%%ROLE_SEF%%' AND %1\$s.roles NOT LIKE '%%ROLE_ADMIN%%' AND %1\$s.roles NOT LIKE '%%ROLE_SUPERADMIN%%')", $alias);
    }
}
