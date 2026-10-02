<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute]
class Report extends Constraint
{

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}