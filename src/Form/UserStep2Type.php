<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

class UserStep2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('first_name', null, [
            'required' => true,
            'constraints' => [new NotBlank(["message" => "Merci de renseigner votre prénom",])],
        ])
        ->add('last_name', null, [
            'required' => true,
            'constraints' => [new NotBlank(["message" => "Merci de renseigner votre nom",])],
        ])
        ->add('company', null, [
            'help' => 'Laissez vide si vous êtes un particulier',
        ])
        ->add('balance_start_period', ChoiceType::class, [
            'choice_loader' => new CallbackChoiceLoader(function() {
                return User::getBalanceStartPeriods();
            }),
            'choice_label' => function ($value, $key, $index) {
                return $value;
            },
            'help' => 'Indiquez le mois auquel débute votre année fiscale (Janvier pour un particulier)'
        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
