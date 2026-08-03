<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SubscriptionInactiveSinceFilterType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'placeholder' => 'Toutes les périodes',
            'choices' => [
                'Expiré aujourd’hui' => '0',
                'Expiré depuis 7 jours maximum' => '7',
                'Expiré depuis 30 jours maximum' => '30',
                'Expiré depuis plus de 30 jours' => 'older_than_30',
            ],
        ]);
    }
}