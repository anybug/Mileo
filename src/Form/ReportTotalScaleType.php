<?php

namespace App\Form;

use App\Entity\VehiculesReport;
use App\Entity\Scale;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReportTotalScaleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $choices = [];

        if(null !== $options['choices']){
            $choices = $options['choices'];
        }

        $builder
            ->add('scale', EntityType::class,[
                'class' => Scale::class,
                'choices' => $choices,
                'label' => false,
                //'preferred_choices' => [$scales[$key]],
                'attr' => ['class' =>'form-select form_scale bg-light text-secondary'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VehiculesReport::class,
            'choices' => null
        ]);
    }
}
