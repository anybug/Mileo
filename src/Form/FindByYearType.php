<?php

namespace App\Form;

use App\Entity\ReportLine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class FindByYearType extends AbstractType
{
    private $entityManager;
    private $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('year', ChoiceType::class, [
                'choices' => $this->getYear(),
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('submit', SubmitType::class,[
                'attr' => [
                    'class' => 'btn btn-primary mt-3 float-end'
                ]
            ])
        ;
    }

    public function getYear() {
        $reportLines = $this->entityManager->getRepository(ReportLine::class)->getLineForUser();
        foreach ($reportLines as $line) {
            $choice[$line->getTravelDate()->format("Y")] = $line->getTravelDate()->format("Y");
        }
        return $choice;
    }
}