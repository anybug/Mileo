<?php
// src/Form/CalendarConnectionType.php

namespace App\Form;

use App\Dto\CalendarConnectionData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class CalendarConnectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $showUrl = $options['show_url'];
        $calendarValidationUrl = $options['calendar_validation_url'];
        $hasSavedPassword = $options['has_saved_password'];
        $credentialsRequired = $options['credentials_required'];

        if ($showUrl) {
            $builder->add('calendarUrl', UrlType::class, [
                'label' => 'Adresse (URL) du calendrier',
                'required' => false,
                'help' => 'Laissez vide si ce membre ne souhaite pas synchroniser de calendrier.',
                'attr' => [
                    'placeholder' => 'https://...',
                    'class' => 'js-calendar-url',
                    'data-calendar-validation-url' => $calendarValidationUrl,
                ],
                'constraints' => [
                    new Url([
                        'message' => 'Merci de renseigner une URL valide.',
                    ]),
                ],
            ]);
        }

        $builder
            ->add('calendarUsername', TextType::class, [
                'label' => "Nom d'utilisateur",
                'required' => false,
                'constraints' => $credentialsRequired ? [
                    new NotBlank([
                        'message' => 'Veuillez renseigner l’identifiant du calendrier.',
                    ]),
                ] : [],
                //'help' => 'À renseigner uniquement si le calendrier nécessite une authentification.',
                'attr' => [
                    'placeholder' => 'Identifiant',
                    'data-caldav-required' => '1',
                    'autocomplete' => 'username',
                ],
            ])
            ->add('plainCalendarPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'required' => false,
                'always_empty' => false,
                'constraints' => $credentialsRequired ? [
                    new NotBlank([
                        'message' => 'Veuillez renseigner le mot de passe d’application du calendrier.',
                    ]),
                ] : [],
                'help' => $hasSavedPassword
                    ? 'Un mot de passe est déjà enregistré. Laissez vide pour le conserver.'
                    : '',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => $hasSavedPassword ? 'Mot de passe déjà enregistré' : '',
                    'data-caldav-required' => '1',
                ],
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $data = $form->getData();

        $hasCalendarUrl = $data instanceof CalendarConnectionData
            && !empty($data->calendarUrl);

        $view->vars['calendar_validation_url'] = $options['calendar_validation_url'];
        $view->vars['credentials_required'] = $options['credentials_required'];
        $view->vars['has_saved_password'] = $options['has_saved_password'];
        $view->vars['has_saved_calendar'] = $options['has_saved_calendar'] || $hasCalendarUrl;
        $view->vars['calendar_user_id'] = $options['calendar_user_id'];
        $view->vars['disable_calendar_url'] = $options['disable_calendar_url'];
        $view->vars['reload_after_disable'] = $options['reload_after_disable'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CalendarConnectionData::class,
            'show_url' => true,
            'calendar_validation_url' => '',
            'has_saved_password' => false,
            'has_saved_calendar' => false,
            'credentials_required' => false,
            'calendar_user_id' => null,
            'disable_calendar_url' => null,
            'reload_after_disable' => false,
        ]);

        $resolver->setAllowedTypes('show_url', 'bool');
        $resolver->setAllowedTypes('calendar_validation_url', 'string');
        $resolver->setAllowedTypes('has_saved_password', 'bool');
        $resolver->setAllowedTypes('has_saved_calendar', 'bool');
        $resolver->setAllowedTypes('credentials_required', 'bool');
        $resolver->setAllowedTypes('calendar_user_id', ['int', 'null']);
        $resolver->setAllowedTypes('disable_calendar_url', ['string', 'null']);
        $resolver->setAllowedTypes('reload_after_disable', 'bool');
        
    }
}