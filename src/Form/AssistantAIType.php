<?php
// src/Form/AssistantAIType.php
namespace App\Form;

use App\Dto\CalendarConnectionData;
use App\Entity\Report;
use App\Entity\User;
use App\Form\CalendarConnectionType;
use App\Service\CalendarReportImporter;
use App\Service\ReportService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssistantAIType extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
        private ReportService $reportService
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $report = $options['report'];

        $builder
            ->add('action', ChoiceType::class, [
                'label' => 'Que souhaitez-vous faire ?',
                'choices' => [
                    'Dupliquer une semaine' => 'duplicate_week',
                    'Dupliquer un trajet' => 'duplicate_trip',
                    'Dupliquer le rapport' => 'duplicate_report',
                    'Importer des trajets depuis un calendrier' => 'import_calendar',
                ],
                'expanded' => true,
                'multiple' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => new TranslatableMessage('Merci de sélectionner une action'),
                    ]),
                ]
            ]);
            
        
        // Champs dynamiques selon l'action
        /*$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($report): void  {
            $form = $event->getForm();
            $data = $event->getData();

            // Par défaut, aucun champ supplémentaire
            $form->add('source_week', ChoiceType::class, [
                'label' => 'Semaine source',
                'choices' => [],
                'required' => false,
                'mapped' => false,
                'placeholder' => 'Sélectionnez une semaine',
                'attr' => ['class' => 'd-none'],
            ]);
            $form->add('trip_id', ChoiceType::class, [
                'label' => 'Trajet source',
                'choices' => [],
                'required' => false,
                'mapped' => false,
                'placeholder' => 'Sélectionnez un trajet',
                'attr' => ['class' => 'd-none'],
            ]);
            $form->add('destination', ChoiceType::class, [
                'label' => 'Destination',
                'choices' => [],
                'required' => false,
                'mapped' => false,
                'placeholder' => 'Sélectionnez une destination',
                'attr' => ['class' => 'd-none'],
            ]);
        });*/

        // Mise à jour dynamique des champs en fonction de l'action
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($report, $options) {
            $data = $event->getData();
            $form = $event->getForm();

            // Supprimer tous les champs dynamiques (pour éviter les doublons)
            $form->remove('source_week');
            $form->remove('trip_id');
            $form->remove('destination');
            $form->remove('target_period');
            $form->remove('copy_mode');
            $form->remove('type_report_line');
            $form->remove('calendarConnection');
            $form->remove('calendar_start_address_choice');

            // Récupérer les périodes cibles disponibles (année + mois sans rapport existant)
            $availablePeriods = $this->reportService->getAvailablePeriodsForReportDuplication($report);

            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'duplicate_week':
                        $form->remove('trip_id');
                        $form->add('source_week', ChoiceType::class, [
                            'label' => 'Semaine source',
                            'choices' => $this->getWeeksForReport($report),
                            'required' => true,
                            'mapped' => false,
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner une semaine source'),
                                ]),
                            ]
                        ]);
                        $form->add('destination', ChoiceType::class, [
                            'label' => 'Destination',
                            'choices' => [
                                'Semaine suivante du mois' => 'next_week',
                                'Toutes les autres semaines du mois' => 'full_month',
                            ],
                            'required' => true,
                            'mapped' => false,
                            'help' => 'Les trajets effectués les jours de la semaine source seront copiés vers la ou les semaine(s) cible(s) en respectant le jour de semaine (borné aux jours du mois).',
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner une destination'),
                                ]),
                            ]
                        ]);
                        break;

                    case 'duplicate_trip':
                        $form->remove('source_week');
                        $form->add('trip_id', ChoiceType::class, [
                            'label' => 'Trajet source',
                            'choices' => $this->getTripsForReport($report),
                            'required' => true,
                            'mapped' => false,
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner un trajet source'),
                                ]),
                            ]
                        ]);
                        $form->add('destination', ChoiceType::class, [
                            'label' => 'Destination',
                            'choices' => [
                                'Toute la semaine (autres jours ouvrables)' => 'whole_week',
                                'Toutes les semaines (même jour de semaine)' => 'all_weeks_same_day',
                                'Tout le mois (jours ouvrables du mois)' => 'all_working_days',
                            ],
                            'required' => true,
                            'mapped' => false,
                            'help' => "
                                        <i>Toute la semaine (jours ouvrables)</i>: le trajet sera dupliqué tous les autres jours ouvrables de sa semaine (lundi au samedi), en omettant le jour même du trajet. <br />
                                        <i>Toutes les semaines (même jour de semaine)</i>: le trajet sera dupliqué le même jour de chaque semaine du mois, sans distinction de jour ouvrable. <br />
                                        <i>Tout le mois (jours ouvrables du mois)</i>: le trajet sera dupliqué tous les autres jours ouvrables du mois (lundi au samedi), en omettant le jour même du trajet. 
                                        ",
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner une destination'),
                                ]),
                            ]
                        ]);
                        break;
                    case 'duplicate_report':
                        $form->add('target_period', ChoiceType::class, [
                            'label' => 'Période cible (année - mois)',
                            'choices' => $availablePeriods,
                            'placeholder' => 'Choisissez une période',
                            'mapped' => false,
                            'required' => true,
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner une période cible'),
                                ]),
                            ]
                        ])
                        ->add('copy_mode', ChoiceType::class, [
                            'label' => 'Mode de copie',
                            'choices' => [
                                'Semaine pour semaine' => 'week_for_week',
                                'Jour pour jour' => 'day_for_day',
                            ],
                            'expanded' => true,
                            'mapped' => false,
                            'required' => true,
                            'data' => 'week_for_week', // Valeur par défaut
                            'help' => "
                                        <i>Semaine pour semaine</i>: trajets copiés selon l'ordre des semaines dans le mois: 1ère semaine du mois source vers 1ère semaine du mois cible, ainsi de suite. <br />
                                        <i>Jour pour jour</i>: trajets copiés selon le numéro de jour dans le mois: 1er du mois source vers 1er du mois cible, ainsi de suite (borné au mois cible). <br />
                                        ",
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner un mode de copie'),
                                ]),
                            ]
                        ]);
                        break;
                    
                    case 'import_calendar':
                        if ($options['show_calendar_url']) {
                            /** @var User|null $user */
                            $user = $options['user'];

                            if (
                                $user instanceof User
                                && (
                                    !isset($data['calendarConnection'])
                                    || !is_array($data['calendarConnection'])
                                    || empty($data['calendarConnection']['calendarUrl'])
                                )
                            ) {
                                $data['calendarConnection'] = [
                                    'calendarUrl' => $user->getCalendarUrl(),
                                    'calendarUsername' => $user->getCalendarUsername(),
                                    'plainCalendarPassword' => '',
                                ];

                                $event->setData($data);
                            }

                            $form->add('calendarConnection', CalendarConnectionType::class, [
                                'mapped' => false,
                                'data' => $this->createCalendarConnectionData($user),
                                'required' => false,
                                'show_url' => true,
                                'calendar_validation_url' => $options['calendar_validation_url'],
                                'has_saved_password' => $options['has_saved_calendar'],
                                'credentials_required' => false,
                            ]);
                        }

                        $form->add('type_report_line', ChoiceType::class, [
                            'label' => 'Mode d’importation des rendez-vous du calendrier',
                            'choices' => [
                                'Classique: chaque trajet est un aller-retour depuis l’adresse de départ' => CalendarReportImporter::MODE_RETURN,
                                'Tournée: chaque trajet débute depuis l’arrivée du trajet précédent' => CalendarReportImporter::MODE_TOUR,
                            ],
                            'expanded' => true,
                            'required' => true,
                            'mapped' => false,
                            'data' => CalendarReportImporter::MODE_RETURN,
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner un mode d’importation des trajets'),
                                ]),
                            ],
                        ]);

                        $form->add('calendar_start_address_choice', ChoiceType::class, [
                            'label' => 'Adresse de départ',
                            'choices' => $this->getAddressChoices($report),
                            'mapped' => false,
                            'required' => true,
                            'placeholder' => '',
                            'attr' => [
                                'class' => 'js-calendar-start-address',
                            ],
                            'constraints' => [
                                new NotBlank([
                                    'message' => new TranslatableMessage('Merci de sélectionner une adresse de départ'),
                                ]),
                            ],
                        ]);

                        break;
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'report' => null,
            'user' => null,
            'calendar_validation_url' => '',
            'show_calendar_url' => true,
            'has_saved_calendar' => false,
        ]);

        $resolver->setRequired('report');

        $resolver->setAllowedTypes('calendar_validation_url', 'string');
        $resolver->setAllowedTypes('show_calendar_url', 'bool');
        $resolver->setAllowedTypes('has_saved_calendar', 'bool');
    }

    // Méthodes utilitaires pour générer les choix
    private function getWeeksForReport(Report $report): array
    {
        $weeks = [];
        $weekCounts = [];

        // Compter le nombre de trajets par semaine
        foreach ($report->getLines() as $line) {
            $date = $line->getTravelDate();
            $weekNumber = (int)$date->format('W');
            $weekCounts[$weekNumber] = ($weekCounts[$weekNumber] ?? 0) + 1;
        }

        // Générer les labels de semaine (du lundi au dimanche)
        foreach ($report->getLines() as $line) {
            $date = $line->getTravelDate();
            $weekNumber = (int)$date->format('W');

            // Calculer le lundi et le dimanche de la semaine
            $monday = (clone $date)->modify('Monday this week');
            $sunday = (clone $monday)->modify('+6 days');

            // Éviter les doublons
            if (!isset($weeks["Semaine $weekNumber"])) {
                $weeks[sprintf(
                    'Semaine %02d - du %s au %s (%d trajet%s)',
                    $weekNumber,
                    $monday->format('d/m/Y'),
                    $sunday->format('d/m/Y'),
                    $weekCounts[$weekNumber] ?? 0,
                    $weekCounts[$weekNumber] > 1 ? 's' : '',
                )] = $weekNumber;
            }
        }

        // Trier par numéro de semaine
        asort($weeks);

        return $weeks;
    }


    private function getTripsForReport(Report $report): array
    {
        $trips = [];
        foreach ($report->getLines() as $line) {
            $tripLabel = sprintf(
                '%s %s : %s → %s (%d km)',
                $this->translator->trans($line->getTravelDate()->format('l')),
                $line->getTravelDate()->format('d/m/Y'),
                $line->getStartAdress(),
                $line->getEndAdress(),
                $line->getKm()
            );
            $trips[$tripLabel] = $line->getId();
        }
        return $trips;
    }

    private function getAddressChoices(Report $report): array
    {
        $user = $report->getUser();

        return !$user->getManagedBy()
            ? $user->getFormattedUserAddresses()
            : $user->getFormattedGroupAddresses();
    }

    private function createCalendarConnectionData(?User $user): CalendarConnectionData
    {
        $data = new CalendarConnectionData();

        if ($user instanceof User) {
            $data->calendarUrl = $user->getCalendarUrl();
            $data->calendarUsername = $user->getCalendarUsername();

            // Seulement si ton mot de passe est stocké de manière réversible.
            $data->plainCalendarPassword = $user->getCalendarEncryptedPassword();
        }

        return $data;
    }
}
