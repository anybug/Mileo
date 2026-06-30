<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SubscriptionExpiryFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [
                'Expires within 30 days' => '30',
                'Expires within 7 days' => '7',
                'Expires today' => '0',
            ],
            'placeholder' => 'All expiry dates',
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}