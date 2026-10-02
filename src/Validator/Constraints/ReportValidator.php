<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Report;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use App\Validator\Constraints\Report as ReportConstraint;

class ReportValidator extends ConstraintValidator
{
    private $entityManager;
    private $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function validate($entity, Constraint $constraint)
    {
        if (!$constraint instanceof ReportConstraint) {
            throw new UnexpectedTypeException($constraint, ReportConstraint::class);
        }

        if (!$entity instanceof Report) {
            throw new UnexpectedValueException($entity, Report::class);
        }

        $startDate = $entity->getStartDate();
        $endDate = $entity->getEndDate();
        $lines = $entity->getLines();
        if (count($lines) == 0) {
            $this->context->buildViolation("Merci de saisir au un moins un trajet dans le rapport")
                    ->addViolation();
        }
        
        $dateViolations = [];
        foreach ($entity->getLines() as $line) {
            if ($line->getTravelDate() < $startDate || $line->getTravelDate() > $endDate) {
                $dateViolations[] = sprintf(
                    "Le trajet du %s ne correspond pas à la période du rapport (%s/%s)",
                    $line->getTravelDate()->format('d-m-Y'),
                    $startDate->format('m'),
                    $endDate->format('Y')
                );
            }
        }

        if(count($dateViolations) > 0){
            foreach($dateViolations as $dateViolation)
            $this->context->buildViolation($dateViolation)
            ->addViolation();
        }
    }
}