<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * PIB: 9 cifara sa ispravnom kontrolnom cifrom (ISO 7064 MOD 11,10).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Pib extends Constraint
{
    public string $message = 'PIB nije ispravan (9 cifara sa kontrolnom cifrom).';
}
