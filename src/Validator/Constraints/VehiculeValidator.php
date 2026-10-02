<?php

namespace App\Validator\Constraints;

use App\Entity\Vehicule;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Security\Core\Security;

class VehiculeValidator extends ConstraintValidator
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function validate($entity, Constraint $constraint)
    {
        $vehicules = $this->security->getUser()->getVehicules();
        $error = true;
        if(count($vehicules) > 0){
            foreach ($vehicules as $vehicule) {
                if($vehicule->isIsDefault()){
                    $error = false;
                }
            }
        } else {
            if (!$entity->isIsDefault()) {
                $error = true;
            }
        }

        if($error){
            $this->context->buildViolation("Merci de sélectionner au moins un véhicule par défaut")
                        ->addViolation();
        }
    }
}