<?php

namespace App\Validator;

use App\Nbs\IdentifierChecksum;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class MaticniBrojValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MaticniBroj) {
            throw new UnexpectedTypeException($constraint, MaticniBroj::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!IdentifierChecksum::isValidMaticniBroj((string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
