<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class BugReportType extends AbstractType
{
    private const CATEGORIES = [
        'bug' => [
            'Connexion / Authentification' => 'auth',
            'Tableau de bord' => 'dashboard',
            'Rapports / Indemnités' => 'reports',
            'Véhicules' => 'vehicules',
            'Facturation / Abonnement' => 'billing',
            'Autre' => 'other',
        ],
        'suggestion' => [
            'Nouvelle fonctionnalité' => 'feature',
            'Amélioration interface' => 'ux',
            'Performance' => 'perf',
            'Autre' => 'other',
        ],
        'question' => [
            'Utilisation de Mileo' => 'usage',
            'Facturation' => 'billing',
            'Autre' => 'other',
        ],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Votre demande concerne...',
                'choices' => [
                    'Un bug' => 'bug',
                    'Une suggestion' => 'suggestion',
                    'Une question' => 'question',
                ],
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'label' => 'Votre message',
                'attr' => ['rows' => 7],
                'constraints' => [new NotBlank()],
            ])
            ->add('screenshot', FileType::class, [
                'mapped' => false,
                'label' => 'Capture d’écran (facultatif)',
                'required' => false,
                'help' => 'Formats acceptés: PNG / JPG / WEBP — 6 Mo max.',
                'constraints' => [
                    new File([
                        'maxSize' => '6M',
                        'mimeTypes' => ['image/png', 'image/jpeg', 'image/webp'],
                        'mimeTypesMessage' => 'Merci d’envoyer une image (PNG/JPG/WEBP).',
                    ])
                ],
            ])
        ;

        $formModifier = function (FormInterface $form, ?string $type) {
            $type = $type ?: 'bug';
            $choices = self::CATEGORIES[$type] ?? [];

            $form->add('category', ChoiceType::class, [
                'choices' => $choices,
                'placeholder' => $choices ? 'Choisissez une catégorie' : 'Aucune catégorie',
                'required' => false,
                'label' => 'Précisions (facultatif)',
            ]);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            $data = $event->getData() ?? [];
            $formModifier($event->getForm(), $data['type'] ?? 'suggestion');
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $data = $event->getData() ?? [];
            $formModifier($event->getForm(), $data['type'] ?? 'suggestion');
        });

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // IMPORTANT: pas d'entité
        ]);
    }
}
