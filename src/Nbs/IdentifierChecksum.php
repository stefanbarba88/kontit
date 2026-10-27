<?php

namespace App\Nbs;

/**
 * Kontrolne cifre srpskih identifikatora firmi.
 *  - PIB: 9 cifara, ISO 7064 MOD 11,10
 *  - Matični broj pravnog lica: 8 cifara, MOD 11 (težine 8..2)
 */
final class IdentifierChecksum
{
    public static function isValidPib(?string $pib): bool
    {
        if (null === $pib || !preg_match('/^\d{9}$/', $pib)) {
            return false;
        }

        $product = 10;
        for ($i = 0; $i < 8; ++$i) {
            $sum = ((int) $pib[$i] + $product) % 10;
            if (0 === $sum) {
                $sum = 10;
            }
            $product = (2 * $sum) % 11;
        }

        return (11 - $product) % 10 === (int) $pib[8];
    }

    public static function isValidMaticniBroj(?string $mb): bool
    {
        if (null === $mb || !preg_match('/^\d{8}$/', $mb)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 7; ++$i) {
            $sum += (int) $mb[$i] * (8 - $i);
        }
        $control = 11 - ($sum % 11);
        if ($control > 9) {
            $control = 0;
        }

        return $control === (int) $mb[7];
    }

    /**
     * NBS vraća matični broj bez vodećih nula (7810318), a u bazi je uvek 8 cifara.
     */
    public static function normalizeMaticniBroj(string $mb): string
    {
        $digits = preg_replace('/\D/', '', $mb) ?? '';

        return '' === $digits ? '' : str_pad($digits, 8, '0', STR_PAD_LEFT);
    }
}
