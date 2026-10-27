<?php

namespace App\Validator;

use App\Nbs\IdentifierChecksum;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PibValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Pib) {
            throw new UnexpectedTypeException($constraint, Pib::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!IdentifierChecksum::isValidPib((string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
