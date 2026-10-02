<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Report;
use Symfony\Component\Security\Core\Security;
use App\Validator\Constraints\NewReport as NewReportConstraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NewReportValidator extends ConstraintValidator
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
        if (!$constraint instanceof NewReportConstraint) {
            throw new UnexpectedTypeException($constraint, NewReportConstraint::class);
        }

        if (!$entity instanceof Report) {
            throw new UnexpectedValueException($entity, Report::class);
        }

        $startDate = $entity->getStartDate();
        $endDate = $entity->getEndDate();

        $reports= $this->entityManager->getRepository(Report::class)->Findby(["user" => $this->security->getToken()->getUser()]);
        foreach ($reports as $report) {
            if($report->getStartDate() == $startDate && $report->getEndDate() == $endDate){
                $this->context->buildViolation("Un rapport pour cette période existe déjà")
                    ->addViolation();
            }
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