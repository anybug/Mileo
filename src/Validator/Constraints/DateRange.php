<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DateRange extends Constraint
{
    public string $message = 'La date doit être comprise entre {{ min_date }} et {{ max_date }}.';
    public string $minMessage = 'La date ne doit pas être antérieure au {{ min_date }}.';
    public string $maxMessage = 'La date ne doit pas être postérieure au {{ max_date }}.';

    public ?string $minDate = null;
    public ?string $maxDate = null;

    public function __construct(mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        if (null === $this->minDate && null === $this->maxDate) {
            throw new \InvalidArgumentException('Au moins une des options "minDate" ou "maxDate" doit être définie.');
        }
    }
}
