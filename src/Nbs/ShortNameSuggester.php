<?php

namespace App\Nbs;

/**
 * Predlog kraćeg naziva klijenta (SPEC 8f.2): zvaničan naziv bez pravne forme i dodataka
 * ("doo", "d.o.o.", "preduzetnik", "PR", "za proizvodnju i promet..."), najviše 40 karaktera.
 */
final class ShortNameSuggester
{
    public const MAX = 40;

    /** Pravna forma kao reč u nazivu: odatle naziv se seče (sve posle je delatnost i sedište) */
    private const FORM = '/(?<![\p{L}\d])(d\.?\s?o\.?\s?o\.?|a\.?\s?d\.?|o\.?\s?d\.?|k\.?\s?d\.?|pr|preduzetnik|preduzetnica|društvo\s+sa\s+ograničenom\s+odgovornošću|akcionarsko\s+društvo|ortačko\s+društvo|komanditno\s+društvo)(?![\p{L}\d])/iu';

    /** Opis na početku naziva koji ne nosi ime ("Privredno društvo za ...", "Preduzetnik") */
    private const PREFIX = '/^((privredno\s+društvo|društvo\s+sa\s+ograničenom\s+odgovornošću|preduzetnik|preduzetnica|preduzeće)\s+|(samostalna\s+)?(\p{L}+(ska|sko|čka)\s+)*radnja\s+)/iu';

    /** Vlasnik posle naziva ("VL. Petar Petrović") */
    private const OWNER = '/\s+vl\..*$/iu';

    /** Kratke reči koje u naslovnom obliku ostaju malim slovima */
    private const SMALL = ['i', 'u', 'za', 'na', 'od', 'sa', 'po', 'iz'];

    public static function suggest(string $name, ?string $nbsShortName = null): string
    {
        $base = trim((string) ($nbsShortName ?: $name));
        $short = preg_replace([self::PREFIX, self::OWNER], '', $base) ?? $base;

        if (preg_match(self::FORM, $short, $m, \PREG_OFFSET_CAPTURE) && $m[0][1] > 0) {
            $short = substr($short, 0, $m[0][1]);
        } elseif (isset($m[0]) && 0 === $m[0][1]) {
            // pravna forma na početku ("DOO Mlekara"): izbaci samo nju
            $short = substr($short, \strlen($m[0][0]));
        }

        $short = trim($short, " \t\n,.-–—\"'„“”");
        if ('' === $short) {
            $short = $base;
        }
        if ($short === mb_strtoupper($short)) {
            $words = explode(' ', mb_convert_case(mb_strtolower($short), \MB_CASE_TITLE));
            foreach ($words as $i => $word) {
                if ($i > 0 && \in_array(mb_strtolower($word), self::SMALL, true)) {
                    $words[$i] = mb_strtolower($word);
                }
            }
            $short = implode(' ', $words);
        }

        return rtrim(mb_substr($short, 0, self::MAX));
    }
}
