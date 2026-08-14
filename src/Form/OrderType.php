<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class OrderType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('billingName', null, [
                'label' => 'Nom ou organisme',
                'required' => true,
                'attr' => [
                    'class' => 'form-control-lg',
                ],
            ])
            ->add('billingAddress', null, [
                'label' => 'Adresse',
                'required' => true,
                'attr' => [
                    'class' => 'form-control-lg',
                ],
            ])
            ->add('billingPostcode', IntegerType::class, [
                'label' => 'Code postal',
                'required' => true,
                'attr' => [
                    'class' => 'form-control-lg',
                ],
            ])
            ->add('billingCity', null, [
                'label' => 'Ville',
                'required' => true,
                'attr' => [
                    'class' => 'form-control-lg',
                ],
            ]);

        if ($options['allow_referral_code']) {
            $builder->add('referralCode', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Code de parrainage',
                'attr' => [
                    'class' => 'form-control-lg',
                    'placeholder' => 'Saisissez votre code de parrainage',
                    'data-referral-target' => 'input',
                ],
                'help' => 'Validez votre code avant de passer au paiement.',
            ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Passer au paiement',
            'attr' => [
                'class' => 'btn-primary p-2',
            ],
        ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'allow_referral_code' => false,
        ]);

        $resolver->setAllowedTypes(
            'allow_referral_code',
            'bool'
        );
    }
}