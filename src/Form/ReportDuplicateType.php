<?php
// src/Form/ReportDuplicateType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ReportDuplicateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $currentYear = (int) date('Y');
        $years = range($currentYear - 3, $currentYear + 1);

        $months = [
            'Janvier' => 1, 'Février' => 2, 'Mars' => 3,
            'Avril' => 4, 'Mai' => 5, 'Juin' => 6,
            'Juillet' => 7, 'Août' => 8, 'Septembre' => 9,
            'Octobre' => 10, 'Novembre' => 11, 'Décembre' => 12,
        ];

        $builder
            ->add('year', ChoiceType::class, [
                'choices' => array_combine($years, $years),
                'label' => 'Année',
            ])
            ->add('month', ChoiceType::class, [
                'choices' => $months,
                'label' => 'Mois',
            ])
            ->add('submit', SubmitType::class, ['label' => 'Créer le duplicata']);
    }
}
