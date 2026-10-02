<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute]
class NewReport extends Constraint
{

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}