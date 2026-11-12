<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * "Vrati me tamo odakle sam došao" posle čuvanja forme. Link ka formi nosi ?returnTo=<trenutna adresa sa filterima i stranom>,
 * a forma ga čuva kao skriveno polje "returnTo" (preživi grešku pri validaciji).
 *
 * BEZBEDNOST (open redirect): prihvata se samo relativna putanja unutar aplikacije: počinje jednim "/", bez "//", bez "\",
 * bez šeme i hosta, bez kontrolnih karaktera. Proverava se i dekodirana vrednost. Sve ostalo se ignoriše (vraća se fallback).
 * Isti servis se koristi za svaku formu (zadatak, kasnije klijent, korisnik, odsustvo).
 */
class ReturnUrlResolver
{
    public const PARAM = 'returnTo';

    public function __construct(private readonly RequestStack $requests)
    {
    }

    /** Ispravna relativna putanja ili null. */
    public function sanitize(mixed $raw): ?string
    {
        if (!\is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ('' === $raw || \strlen($raw) > 2000 || !str_starts_with($raw, '/')) {
            return null;
        }
        // proverava se i sirova i (dvaput) dekodirana vrednost: "/%5Cevil.com", "/%2F%2Fevil.com" i slično
        $decoded = [$raw, rawurldecode($raw), rawurldecode(rawurldecode($raw))];
        foreach ($decoded as $value) {
            if (str_starts_with($value, '//') || str_contains($value, '\\') || preg_match('/[\x00-\x1F\x7F]/', $value) || !str_starts_with($value, '/')) {
                return null;
            }
        }
        // ništa što liči na šemu ili host u delu pre upitnika/heša ("/x:@evil.com" nije problem, ali "/@evil.com" ostaje putanja)
        $parts = parse_url($raw);
        if (false === $parts || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        return $raw;
    }

    public function resolve(mixed $raw, string $fallback): string
    {
        return $this->sanitize($raw) ?? $fallback;
    }

    /** returnTo iz forme (POST ima prednost) ili iz adrese; ispravan ili null. */
    public function fromRequest(Request $request): ?string
    {
        return $this->sanitize($request->request->get(self::PARAM) ?? $request->query->get(self::PARAM));
    }

    /** Trenutna adresa (sa filterima i stranom) za returnTo u linkovima; samo za GET stranice, inače null. */
    public function current(): ?string
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request || !$request->isMethod('GET')) {
            return null;
        }

        return $this->sanitize($request->getRequestUri());
    }
}
