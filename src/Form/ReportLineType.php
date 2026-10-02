<?php

namespace App\Form;

use App\Entity\ReportLine;
use App\Entity\Scale;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ReportLineType extends AbstractType
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
    $this->tokenStorage = $tokenStorage;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('travel_date', DateType::class, [
        'widget' => 'single_text',
        'required' => true,
        'label_attr' => ['class' => 'required'],
        'attr' => [
            'class' => 'report_lines_travel_date',
            'required' => 'required',
            ]
        ]);

        $builder->add('vehicule', EntityType::class, [
            "class" => Vehicule::class,
            'required' => true,
            'label_attr' => ['class' => 'required'],
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('v')
                    ->andWhere('v.user = (:user)')
                    ->setParameter('user', $this->tokenStorage->getToken()->getUser());
            },
            'preferred_choices' => [$this->tokenStorage->getToken()->getUser()->getDefaultVehicule()],
            'attr' => ['class'=>'report_lines_vehicule','required' => 'required']
        ]);

        $builder->add('startAdress', null,  [
            'required' => true,
            'label_html' => true,
            'label_attr' => ['class' => 'required'],
            'help' => 'Saisissez une adresse ou <a class="popup-fav-lines-start">selectionnez une de vos <i class="fa fa-map-marker-alt"></i></a>',
            'attr' => ['class' => 'autocomplete report_lines_start','required' => 'required']
        ]);

        $builder->add('endAdress', null,  [
            'required' => true,
            'label_html' => true,
            'label_attr' => ['class' => 'required'],
            'help' => 'Saisissez une adresse ou <a class="popup-fav-lines-end">selectionnez une de vos <i class="fa fa-map-marker-alt"></i></a>',
            'attr' => ['class' => 'autocomplete report_lines_end','required' => 'required']
        ]);

        $builder->add('km', HiddenType::class, ['label_attr' => ['class' => 'required'],'required' => true,'attr' => ['readonly'=> true, 'class' => 'report_lines_km','required' => 'required']]);
        $builder->add('is_return', CheckboxType::class, ['required' => true, 'attr' => ['class' => 'report_lines_is_return']]);
        $builder->add('km_total', null, ['label_attr' => ['class' => 'required'],'required' => true,'attr' => ['readonly'=> true, 'class' => 'report_lines_km_total','required' => 'required']]);
        $builder->add('comment', TextareaType::class ,['required' => true, 'label' => 'Motif du déplacement','label_attr' => ['class' => 'required'], 'attr' => ['required' => 'required','class ' => 'report_lines_comment form-control', 'placeholder' => "Saisissez une courte description qui justifie ce trajet"]]);
        
        $builder->add('amount', null, ['label_attr' => ['class' => 'required'],'required' => true,'attr' => ['readonly'=> true, 'class' => 'report_lines_amount bg-light', 'required' => 'required']]);
    }

    public function getName()
    {
        return 'reportline';
    }

    
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReportLine::class,
        ]);
    }
}    