<?php

namespace App\Service;

/**
 * Normalizacija teksta za pretragu bez razlike u velikim/malim slovima i kvačicama: "Račun" → "racun", "Đorđe" i "Djordje" → "dorde".
 * Ćirilica se prevodi u latinicu. Ista funkcija se primenjuje na tekst u bazi (npr. task.title_search) i na uneti izraz,
 * pa pretraga ne zavisi od collation-a baze (MariaDB i MySQL 8 se ponašaju različito za đ).
 * Koristi se za pretragu zadataka, a isto je predviđeno i za klijente.
 */
final class TextNormalizer
{
    private static ?\Transliterator $transliterator = null;

    public function normalize(?string $text): string
    {
        $text = trim((string) $text);
        if ('' === $text) {
            return '';
        }
        self::$transliterator ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()') ?: null;
        $text = null !== self::$transliterator ? (self::$transliterator->transliterate($text) ?: $text) : mb_strtolower($text);
        // "dj" i "đ" se poklapaju sa "d" na obe strane (Đorđe = Djordje = dorde)
        $text = str_replace('dj', 'd', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
