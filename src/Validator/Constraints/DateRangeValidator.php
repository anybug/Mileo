<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class DateRangeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof DateRange) {
            throw new UnexpectedTypeException($constraint, DateRange::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof \DateTimeInterface) {
            throw new UnexpectedValueException($value, \DateTimeInterface::class);
        }

        $minDate = null;
        $maxDate = null;

        if (null !== $constraint->minDate) {
            $minDate = new \DateTimeImmutable($constraint->minDate);
        }

        if (null !== $constraint->maxDate) {
            $maxDate = new \DateTimeImmutable($constraint->maxDate);
        }

        if (null !== $minDate && $value < $minDate) {
            $this->context->buildViolation(
                null !== $maxDate ? $constraint->message : $constraint->minMessage
            )
                ->setParameter('{{ min_date }}', $minDate->format('d/m/Y'))
                ->setParameter('{{ max_date }}', null !== $maxDate ? $maxDate->format('d/m/Y') : '')
                ->addViolation();

            return;
        }

        if (null !== $maxDate && $value > $maxDate) {
            $this->context->buildViolation(
                null !== $minDate ? $constraint->message : $constraint->maxMessage
            )
                ->setParameter('{{ min_date }}', null !== $minDate ? $minDate->format('d/m/Y') : '')
                ->setParameter('{{ max_date }}', $maxDate->format('d/m/Y'))
                ->addViolation();
        }
    }
}