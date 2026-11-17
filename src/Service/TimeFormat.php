<?php

namespace App\Service;

/**
 * Jedino mesto za format vremena u aplikaciji (SPEC 8p.6): 24h, bez AM/PM. Format se menja ovde, a korak izbora u
 * parametru `app.time.step_minutes` (config/services.yaml). Prikaz u šablonima ide kroz filtere kt_time / kt_datetime /
 * kt_datetime_short (src/Twig/TimeExtension.php), a PHP kod koristi ove konstante.
 */
final class TimeFormat
{
    public const TIME = 'H:i';
    public const DATETIME = 'd.m.Y. H:i';
    public const DATETIME_SHORT = 'd.m. H:i';
    /** "17.11.2026. u 14:30" (obaveštenja) */
    public const DATETIME_AT = 'd.m.Y. \u H:i';

    /**
     * Ponuđena vremena u koracima ("00:00", "00:30" ... "23:30"). Postojeća vrednost van koraka (npr. "14:15") se ne gubi:
     * umeće se na svoje mesto u redosledu.
     *
     * @return list<string>
     */
    public static function options(int $stepMinutes, ?string $existing = null): array
    {
        $step = max(1, min(720, $stepMinutes));
        $times = [];
        for ($m = 0; $m < 1440; $m += $step) {
            $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
        if (null !== $existing && 1 === preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $existing) && !\in_array($existing, $times, true)) {
            $times[] = $existing;
            sort($times, SORT_STRING);
        }

        return $times;
    }
}
