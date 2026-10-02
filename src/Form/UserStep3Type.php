<?php

namespace App\Form;

use App\Entity\Vehicule;
use App\Entity\Power;
use App\Entity\Scale;
use App\Entity\Brand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Doctrine\ORM\EntityManagerInterface;

class UserStep3Type extends AbstractType
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    private $scaleHelp = "Ce barème sera utilisé pour estimer les indemnités en temps réel, il vous sera demandé de le réajuster lors de l'édition du rapport annuel si besoin";

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => ['Voiture' => 'Car', 'Moto/Cyclo' => 'Cyclo'],
                'expanded' => true, 
                'required' => true,
                'choice_attr' => function($choice, $key, $value) {
                    return ['class' => 'vehicule_type'];
                }
            ])

            ->add('brand', EntityType::class, [
                'class' => Brand::class,
                'required' => false,
                'label_attr' => ['class' => 'required'],
                'attr' => ['data-placeholder' => " ", 'class' => 'vehicule_brand', 'data-ea-widget' => "ea-autocomplete", 'required' => 'required']
            ])
            
            ->add('model')

            ->add('power', EntityType::class, [
                'required' => true,
                'class' => Power::class,
                'choices' => [],
                'label' => 'Puissance Fiscale',
                'attr' => ['data-placeholder' => " ", 'class' => 'bg-light vehicule_power', 'data-ea-widget' => "ea-autocomplete", 'disabled' => 'disabled']
            ])

            ->add('scale', EntityType::class, [
                'required' => true,
                'class' => Scale::class,
                'choices' => [],
                'label' => "Barème : estimation de la distance annuelle parcourue",
                'attr' => ['data-placeholder' => " ", 'class' => 'bg-light vehicule_scale', 'data-ea-widget' => "ea-autocomplete", 'disabled' => 'disabled'],
                'help' => $this->scaleHelp
            ])

        ;

        $formModifierType = function (FormInterface $form, $type = null) 
        {
            if($type !== null){
                $form->add('power', EntityType::class, [
                    'required' => true,
                    'class' => Power::class,
                    'query_builder' => function (EntityRepository $er) use($type){
                        return $er->createQueryBuilder('p')
                            ->andWhere('p.type = (:type)')
                            ->setParameter('type', $type);
                        },
                    'attr' => ['class' => 'vehicule_power', 'data-ea-widget' => 'ea-autocomplete']
                ]);
            }
        };

        $formModifierPower = function (FormInterface $form, Power $power = null) 
        {
            if($power !== null){
                $scales = $power->getScales();  
                foreach($scales as $s)
                {
                    $choices[$s->getYear()][(string) $s->getPower()->__toString()][(string) $s->__toString()] = $s;
                }

                ksort($choices);
                $final_choices = array_pop($choices);

                $form->add('scale', EntityType::class, [
                    'class' => Scale::class,
                    'required' => true,
                    'label' => "Barème : estimation de la distance annuelle parcourue",
                    'choices' => $final_choices,
                    'attr' => ['class' => 'vehicule_scale', 'data-ea-widget' => 'ea-autocomplete'],
                    'help' => $this->scaleHelp
                ]);
            }
        };

        /*$builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifierType, $formModifierPower) {
                $data = $event->getData();
                $formModifierPower($event->getForm(), $data->getPower());
                $formModifierType($event->getForm(), $data->getType());
            }
        );*/

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formModifierPower, $formModifierType) {
                $data = $event->getData();
                $powerId = array_key_exists('power', $data) ? $data['power'] : false;
                if($powerId){
                    $power = $this->entityManager->getRepository(Power::class)->find($powerId);
                }else{
                    $power = null;
                }
                
                $type = array_key_exists('type', $data) ? $data['type'] : null;

                $formModifierType($event->getForm(), $type);
                $formModifierPower($event->getForm(), $power);
            }
        );
    }
    

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Vehicule::class
        ]);
    }

}
