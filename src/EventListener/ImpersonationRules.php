<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Request;

/**
 * Pravila impersonacije (SPEC 8s) kao čista logika bez sesije i tokena, da se lako testiraju.
 * Jedino mesto gde su nabrojane rute: privatne (404), zabranjene čak i u režimu izmena, i poruke.
 */
final class ImpersonationRules
{
    public const VIEW_ONLY = 'Samo pregled: izmene nisu dozvoljene.';
    public const FORBIDDEN = 'Ova radnja nije dozvoljena u ime korisnika.';

    /** Podsetnik: strogo privatan, za vreme impersonacije ne postoji (404). */
    public function isPrivate(string $route): bool
    {
        return str_starts_with($route, 'todo_') || str_starts_with($route, 'ajax_todo');
    }

    /** GET rute koje bi nešto promenile (npr. povezivanje naloga): zabranjene u oba režima. */
    public function forbidsRead(string $route): bool
    {
        return false;
    }

    /**
     * Poruka zašto je zahtev zabranjen, ili null ako sme.
     * Poziva se za izmene (POST/PUT/PATCH/DELETE) i za rute iz forbidsRead().
     */
    public function denial(string $route, Request $request, bool $editing): ?string
    {
        if (!$editing) {
            return self::VIEW_ONLY;
        }

        return null;
    }
}
