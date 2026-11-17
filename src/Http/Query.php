<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\InputBag;

/**
 * Tolerantno čitanje parametara zahteva (query i form polja).
 * InputBag::getInt() baca BadRequestException (HTTP 500 u AJAX-u) za prazan ili neispravan string,
 * a JS i prazne opcije selecta ("Svi timovi") često šalju upravo `key=`.
 * Ovde se neispravna vrednost ignoriše i vraća podrazumevana.
 */
final class Query
{
    public static function int(InputBag $bag, string $key, ?int $default = null): ?int
    {
        $value = $bag->all()[$key] ?? null;
        if (!\is_scalar($value) || \is_bool($value)) {
            return $default;
        }
        $int = filter_var(trim((string) $value), \FILTER_VALIDATE_INT);

        return false === $int ? $default : $int;
    }

    /** Pozitivan id ili null (prazno, 0, negativno ili neispravno). */
    public static function id(InputBag $bag, string $key): ?int
    {
        $int = self::int($bag, $key);

        return null !== $int && $int > 0 ? $int : null;
    }

    /** Boolean bez izuzetka; prihvata 1/0, true/false, on/off, yes/no, a sve ostalo je false. */
    public static function bool(InputBag $bag, string $key): bool
    {
        $value = $bag->all()[$key] ?? null;

        return \is_scalar($value) && true === filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
    }
}
