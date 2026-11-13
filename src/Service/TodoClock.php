<?php

namespace App\Service;

/**
 * JEDINO mesto koje računa vremensku zonu Podsetnika. U bazi je sve UTC, korisnik vidi i bira Europe/Belgrade.
 * Kontroleri, Twig i servisi ne računaju zonu ručno: traže ovde "sada", "početak dana", "lokalni datum + vreme u UTC".
 * Poređenja ("zakasnelo", "danas", "stiglo za slanje") rade u PHP-u sa ovim UTC vrednostima, nikad NOW() iz baze.
 */
class TodoClock
{
    public const ZONE = 'Europe/Belgrade';

    private ?\DateTimeImmutable $fixedNow = null;

    /** Samo za testove: zamrzava "sada" */
    public function setNow(?\DateTimeImmutable $now): void
    {
        $this->fixedNow = $now?->setTimezone(new \DateTimeZone('UTC'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->fixedNow ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function zone(): \DateTimeZone
    {
        return new \DateTimeZone(self::ZONE);
    }

    public function toLocal(\DateTimeInterface $moment): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($moment)->setTimezone($this->zone());
    }

    /** Lokalni datum (Y-m-d) trenutka */
    public function localDate(\DateTimeInterface $moment): string
    {
        return $this->toLocal($moment)->format('Y-m-d');
    }

    /** Lokalno vreme (H:i) trenutka */
    public function localTime(\DateTimeInterface $moment): string
    {
        return $this->toLocal($moment)->format('H:i');
    }

    /** Današnji lokalni datum (Y-m-d) */
    public function today(): string
    {
        return $this->localDate($this->now());
    }

    /** Lokalni datum i vreme (npr. "2026-11-13", "09:00") kao trenutak u UTC; bez vremena je to ponoć tog dana */
    public function fromLocal(string $date, ?string $time = null): \DateTimeImmutable
    {
        $local = new \DateTimeImmutable($date.' '.($time ?? '00:00'), $this->zone());

        return $local->setTimezone(new \DateTimeZone('UTC'));
    }

    /** UTC trenutak početka lokalnog dana */
    public function startOfDay(string $date): \DateTimeImmutable
    {
        return $this->fromLocal($date);
    }

    /** UTC trenutak početka sutrašnjeg lokalnog dana (granica "zakasnelo + danas") */
    public function startOfTomorrow(): \DateTimeImmutable
    {
        return $this->startOfDay((new \DateTimeImmutable($this->today()))->modify('+1 day')->format('Y-m-d'));
    }
}
