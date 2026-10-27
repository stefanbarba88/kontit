<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Matični broj pravnog lica: 8 cifara sa ispravnom kontrolnom cifrom (MOD 11).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MaticniBroj extends Constraint
{
    public string $message = 'Matični broj nije ispravan (8 cifara sa kontrolnom cifrom).';
}
