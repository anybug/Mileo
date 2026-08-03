<?php

namespace App\Controller\Team;

use App\Controller\App\CalendarUserCrudController;
use App\Dto\CalendarConnectionData;
use App\Entity\Subscription;
use App\Entity\User;
use App\Form\CalendarConnectionType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use App\Form\CollaboratorExitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[IsGranted('ROLE_MANAGER')]
class TeamUserCrudController extends AbstractCrudController
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        UserPasswordHasherInterface $passwordHasher,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->passwordHasher = $passwordHasher;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Membre')
            ->setEntityLabelInPlural('Membres')
            ->setPageTitle(Crud::PAGE_INDEX, 'Membres collaborateurs de l\'équipe <br /><span class="fs-6 fw-normal">Gestion de l\'effectif de votre équipe: chacun des membres peut se connecter à la plateforme indépendamment afin d\'effectuer sa saisie en toute autonomie. <br />Vous pouvez aussi vous connecter à leur compte à des fins de saisie ou de vérification.</span>')
            ->setDefaultSort(['last_name' => 'ASC', 'first_name' => 'ASC'])
            ->setSearchFields(['first_name', 'last_name', 'email'])
            ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
            ->setFormThemes([
                '@EasyAdmin/crud/form_theme.html.twig',
                'App/Form/calendar_connection_theme.html.twig',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $impersonate = Action::new(
            'impersonate',
            'Se connecter',
            'fa-solid fa-person-walking-arrow-right'
        )
            ->linkToUrl(function (User $user): string {
                return $this->generateUrl('app', [
                    '_switch_user' => $user->getEmail(),
                ]);
            })
            ->displayIf(function (User $user): bool {
                /** @var User $manager */
                $manager = $this->getUser();

                return $user->getManagedBy()?->getId()
                    === $manager->getId();
            });

        $leaveWorkforce = Action::new(
            'leaveWorkforce',
            'Sortir de l’effectif',
            'fa-solid fa-user-minus'
        )
            ->linkToCrudAction('leaveWorkforce')
            ->addCssClass('btn btn-danger')
            ->displayIf(function (User $user): bool {
                /** @var User $manager */
                $manager = $this->getUser();

                return $user->getManagedBy()?->getId()
                    === $manager->getId()
                    && null === $user->getWorkforceExitDate();
            });

        $restoreWorkforce = Action::new(
            'restoreWorkforce',
            'Réintégrer dans l’effectif',
            'fa-solid fa-user-check'
        )
            ->linkToCrudAction('restoreWorkforce')
            ->addCssClass('btn btn-success')
            ->askConfirmation(
                'Voulez-vous réactiver ce collaborateur et le réintégrer dans l’effectif ?',
                'Réactiver'
            )
            ->displayIf(function (User $user): bool {
                /** @var User $manager */
                $manager = $this->getUser();

                return $user->getManagedBy()?->getId()
                    === $manager->getId()
                    && null !== $user->getWorkforceExitDate();
            });

        $canEdit = function (User $user): bool {
            /** @var User $manager */
            $manager = $this->getUser();

            return $user->getId() !== $manager->getId()
                && $user->getManagedBy()?->getId() === $manager->getId()
                && null === $user->getWorkforceExitDate();
        };

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $impersonate)

            ->add(Crud::PAGE_DETAIL, $leaveWorkforce)
            ->add(Crud::PAGE_DETAIL, $restoreWorkforce)

            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->disable(Action::DELETE)

            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                static fn (Action $action): Action =>
                    $action->displayIf($canEdit)
            )

            ->update(
                Crud::PAGE_DETAIL,
                Action::EDIT,
                static fn (Action $action): Action =>
                    $action->displayIf($canEdit)
            );
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        /** @var User $me */
        $me = $this->getUser();

        $qb->andWhere('entity.managedBy = :me')
            ->orWhere('entity = :me')
            ->setParameter('me', $me);

        return $qb;
    }

    public function createEntity(string $entityFqcn)
    {
        /** @var User $me */
        $me = $this->getUser();

        $member = new $entityFqcn;
        $member->setCompany($me->getCompany());
        $member->setBalanceStartPeriod($me->getBalanceStartPeriod());

        return $member;
    }

    public function edit(AdminContext $context)
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        /** @var User $me */
        $me = $this->getUser();

        if ($user->getManagedBy()?->getId() !== $me->getId()) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $target = $context->getEntity()->getInstance();

        if (!$target instanceof User) {
            throw new AccessDeniedHttpException();
        }

        /** @var User $manager */
        $manager = $this->getUser();

        $isManagerHimself = $target->getId() === $manager->getId();

        $isManagedMember = $target->getManagedBy()?->getId()
            === $manager->getId();

        if (!$isManagerHimself && !$isManagedMember) {
            throw new AccessDeniedHttpException();
        }

        return parent::detail($context);
    }

    public function delete(AdminContext $context)
    {
        /** @var User $target */
        $target = $context->getEntity()->getInstance();

        /** @var User $me */
        $me = $this->getUser();

        if ($target->getManagedBy()?->getId() !== $me->getId()) {
            throw new AccessDeniedHttpException();
        }

        if ($target->getId() === $me->getId()) {
            throw new AccessDeniedHttpException();
        }

        return parent::delete($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            /** @var User $me */
            $me = $this->getUser();

            $entityInstance->setManagedBy($me);
            $entityInstance->setRoles(['ROLE_USER']);

            $this->copySubscriptionFromManager($entityInstance, $me);

            $this->encodePassword($entityInstance);

            $this->applyCalendarConnectionData($entityInstance);
            $this->encodeCalendarPassword($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }


    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->encodePassword($entityInstance);

            $this->applyCalendarConnectionData($entityInstance);
            $this->encodeCalendarPassword($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function encodePassword(User $user): void
    {
        if ($user->getPlainPassword() != null) {
            $hash = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($hash);
        }
    }

    private function copySubscriptionFromManager(User $member, User $manager): void
    {
        $managerSub = $manager->getSubscription();
        if (!$managerSub) {
            throw new \LogicException('Le manager n’a pas de subscription : impossible de créer un membre.');
        }

        if ($member->getSubscription()) {
            return;
        }

        $sub = new Subscription();

        $sub->setPlan($managerSub->getPlan());
        $sub->setSubscriptionStart(clone $managerSub->getSubscriptionStart());
        $sub->setSubscriptionEnd(clone $managerSub->getSubscriptionEnd());

        $sub->setUser($member);

        $member->setSubscription($sub);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield Field::new('first_name', 'Prénom');
            yield Field::new('last_name', 'Nom');
            yield EmailField::new('email', 'E-mail');

            if ($pageName === Crud::PAGE_INDEX) {
                yield BooleanField::new(
                    'inWorkforce',
                    'Dans l’effectif'
                )
                    ->renderAsSwitch(false);
            }

            yield DateTimeField::new('last_login', 'Dernière connexion');
            yield CollectionField::new('reports', 'Nb reports');
            return;
        }

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Informations personnelles')->setIcon('fa fa fa-id-card');
            yield Field::new('first_name')->setFormTypeOptions(['required' => true])->setColumns(6);
            yield Field::new('last_name')->setFormTypeOptions(['required' => true])->setColumns(6);
            yield Field::new('company');
            yield ChoiceField::new('balanceStartPeriod')
                ->setColumns('col-12')
                //->setHelp('Modifier votre période fiscale modifie également celle de vos collaborateurs')
                ->setChoices(fn () => [
                    'Janvier' => 'January',
                    'Février' => 'February',
                    'Mars' => 'March',
                    'Avril' => 'April',
                    'Mai' => 'May',
                    'Juin' => 'June',
                    'Juillet' => 'July',
                    'Août' => 'August',
                    'Septembre' => 'September',
                    'Octobre' => 'October',
                    'Novembre' => 'November',
                    'Décembre' => 'December',
                ]);
            
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Profil')->setIcon('fa fa fa-user');
            yield Field::new('email', 'Adresse e-mail')->setHelp('L\'adresse e-mail est utilisée comme nom d\'utilisateur pour se connecter à la plateforme');
            yield Field::new('plainPassword')
                ->setFormType(RepeatedType::class)
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->setFormTypeOptions([
                    'required' => $pageName === Crud::PAGE_NEW,
                    'options' => [
                        'attr' => [
                            'autocomplete' => 'new-password',
                        ],
                    ],
                    'type' => PasswordType::class,
                    'first_options' => [
                        'label' => 'Mot de passe',
                        'required' => $pageName === Crud::PAGE_NEW,
                    ],
                    'second_options' => [
                        'label' => 'Confirmation du mot de passe',
                        'required' => $pageName === Crud::PAGE_NEW,
                    ],
                    'invalid_message' => 'Les mots de passe ne correspondent pas.',
                ])
                ->setHelp(
                    $pageName === Crud::PAGE_EDIT
                        ? 'Laissez vide pour conserver le mot de passe actuel.'
                        : ''
                );
            yield BooleanField::new('active', 'Profil activé')->setHelp("Si désactivé, l'utilisateur ne peut pas se connecter à la plateforme");

            $context = $this->getContext();
            $editedUser = $context?->getEntity()?->getInstance();

            if ($editedUser instanceof User) {
                $calendarValidationUrl = $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(CalendarUserCrudController::class)
                    ->setAction('validateCalendarUrl')
                    ->setEntityId($editedUser->getId())
                    ->generateUrl();

                yield FormField::addColumn(12);
                yield FormField::addFieldset('Calendrier du membre')
                    ->setIcon('fa fa-calendar-days');

                yield $this->getCalendarConnectionField(
                    $editedUser,
                    $calendarValidationUrl
                );
            }

            return;
        }

    }

    private function getCalendarConnectionData(User $user): CalendarConnectionData
    {
        $data = new CalendarConnectionData();
        $data->calendarUrl = $user->getCalendarUrl();
        $data->calendarUsername = $user->getCalendarUsername();

        return $data;
    }

    private function getCalendarConnectionField(User $user, string $calendarValidationUrl): Field
    {
        return Field::new('calendarConnection', 'Calendrier')
            ->onlyOnForms()
            ->setFormType(CalendarConnectionType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'data' => $this->getCalendarConnectionData($user),
                'show_url' => true,
                'has_saved_password' => !empty($user->getCalendarEncryptedPassword()),
                'calendar_validation_url' => $calendarValidationUrl,
                'calendar_user_id' => $user->getId(),
                'disable_calendar_url' => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(CalendarUserCrudController::class)
                    ->setAction('disableCalendar')
                    ->setEntityId($user->getId())
                    ->generateUrl(),
                'reload_after_disable' => true,
            ])
            /* Si on souhaite désactiver les champs de connexion au calendrier lorsque l'URL est déjà renseignée
            Si plus tard option pour supprimer le calendrier synchronisé
            ->setFormTypeOptions([
                'mapped' => false,
                'data' => $this->getCalendarConnectionData($user),
                'show_url' => true,
                'has_saved_password' => !empty($user->getCalendarEncryptedPassword()),
                'calendar_validation_url' => $calendarValidationUrl,
                'disabled' => !empty($user->getCalendarUrl()),
            ])*/
            ->setColumns(12);
    }

    private function applyCalendarConnectionData(User $user): void
    {
        $request = $this->getContext()?->getRequest();

        if (!$request) {
            return;
        }

        $calendarConnection = null;

        foreach ($request->request->all() as $formData) {
            if (!is_array($formData)) {
                continue;
            }

            if (
                isset($formData['calendarConnection'])
                && is_array($formData['calendarConnection'])
            ) {
                $calendarConnection = $formData['calendarConnection'];
                break;
            }
        }

        if (!is_array($calendarConnection)) {
            return;
        }

        $calendarUrl = trim((string) ($calendarConnection['calendarUrl'] ?? ''));
        $calendarUsername = trim((string) ($calendarConnection['calendarUsername'] ?? ''));
        $plainCalendarPassword = trim((string) ($calendarConnection['plainCalendarPassword'] ?? ''));

        if ($calendarUrl === '') {
            $user->setCalendarUrl(null);
            $user->setCalendarUsername(null);
            $user->setPlainCalendarPassword(null);
            $user->setCalendarEncryptedPassword(null);
            $user->setCalendarSynchronized(false);

            return;
        }

        $user->setCalendarUrl($calendarUrl);
        $user->setCalendarUsername($calendarUsername !== '' ? $calendarUsername : null);

        if ($plainCalendarPassword !== '') {
            $user->setPlainCalendarPassword($plainCalendarPassword);
        }

        $user->setCalendarSynchronized(true);
    }

    private function encodeCalendarPassword(User $user): void
    {
        if (!$user->getPlainCalendarPassword()) {
            return;
        }

        // Temporaire : stocke en clair, à remplacer par un vrai chiffrement.
        $user->setCalendarEncryptedPassword($user->getPlainCalendarPassword());
        $user->setPlainCalendarPassword(null);
    }

    #[AdminRoute(
        path: '/{entityId}/reintegration-effectif',
        name: 'restore_workforce'
    )]
    public function restoreWorkforce(
        Request $request
    ): Response {
        $entityId = (int) $request->attributes->get(
            'entityId',
            0
        );

        if ($entityId <= 0) {
            throw $this->createNotFoundException(
                'Identifiant du collaborateur invalide.'
            );
        }

        $collaborator = $this->entityManager
            ->getRepository(User::class)
            ->find($entityId);

        if (!$collaborator instanceof User) {
            throw $this->createNotFoundException(
                'Collaborateur introuvable.'
            );
        }

        /** @var User $manager */
        $manager = $this->getUser();

        /*
        * Le manager ne peut réintégrer que l’un de ses propres
        * collaborateurs.
        */
        if (
            $collaborator->getManagedBy()?->getId()
            !== $manager->getId()
        ) {
            throw new AccessDeniedHttpException();
        }

        if (null === $collaborator->getWorkforceExitDate()) {
            $this->addFlash(
                'warning',
                'Ce collaborateur fait déjà partie de l’effectif.'
            );

            return $this->redirectToCollaboratorDetail(
                $collaborator
            );
        }

        $collaborator
            ->setWorkforceExitDate(null)
            ->setActive(true);

        $this->entityManager->flush();

        $this->addFlash(
            'success',
            sprintf(
                '%s a été réintégré dans l’effectif et son compte a été réactivé.',
                (string) $collaborator
            )
        );

        return $this->redirectToCollaboratorDetail(
            $collaborator
        );
    }

    #[AdminRoute(
        path: '/{entityId}/sortie-effectif',
        name: 'leave_workforce'
    )]
    public function leaveWorkforce(
        Request $request
    ): Response {
        $entityId = $request->attributes->get('entityId');

        if (
            !is_string($entityId)
            && !is_int($entityId)
        ) {
            throw $this->createNotFoundException(
                'Identifiant du collaborateur manquant.'
            );
        }

        if (
            !ctype_digit((string) $entityId)
            || (int) $entityId <= 0
        ) {
            throw $this->createNotFoundException(
                'Identifiant du collaborateur invalide.'
            );
        }

        $collaborator = $this->entityManager
            ->getRepository(User::class)
            ->find((int) $entityId);

        if (!$collaborator instanceof User) {
            throw $this->createNotFoundException(
                'Collaborateur introuvable.'
            );
        }

        /** @var User $manager */
        $manager = $this->getUser();

        if (
            $collaborator->getManagedBy()?->getId()
            !== $manager->getId()
        ) {
            throw new AccessDeniedHttpException();
        }

        if (null !== $collaborator->getWorkforceExitDate()) {
            $this->addFlash(
                'warning',
                'Ce collaborateur est déjà sorti de l’effectif.'
            );

            return $this->redirectToCollaboratorDetail(
                $collaborator
            );
        }

        $lastReportEndDate = $this->getLastReportEndDate(
            $collaborator
        );

        $form = $this->createForm(
            CollaboratorExitType::class,
            null,
            [
                'minimum_exit_date' => $lastReportEndDate,
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{exitDate?: \DateTimeImmutable}|null $data */
            $data = $form->getData();

            $exitDate = is_array($data)
                ? ($data['exitDate'] ?? null)
                : null;

            if (!$exitDate instanceof \DateTimeImmutable) {
                $this->addFlash(
                    'danger',
                    'La date de sortie est invalide.'
                );
            } elseif (
                null !== $lastReportEndDate
                && $exitDate <= $lastReportEndDate
            ) {
                $this->addFlash(
                    'danger',
                    sprintf(
                        'La date de sortie doit être postérieure au %s.',
                        $lastReportEndDate->format('d/m/Y')
                    )
                );
            } else {
                $collaborator
                    ->setWorkforceExitDate($exitDate);

                $this->entityManager->flush();

                $this->addFlash(
                    'success',
                    sprintf(
                        '%s est sorti de l’effectif le %s.',
                        (string) $collaborator,
                        $exitDate->format('d/m/Y')
                    )
                );

                return $this->redirectToCollaboratorDetail(
                    $collaborator
                );
            }
        }

        return $this->render(
            'Team/Collaborator/leave_workforce.html.twig',
            [
                'collaborator' => $collaborator,
                'lastReportEndDate' => $lastReportEndDate,
                'form' => $form,
            ]
        );
    }

    private function getLastReportEndDate(
        User $collaborator
    ): ?\DateTimeImmutable {
        $lastReportEndDate = null;

        foreach ($collaborator->getReports() as $report) {
            $endDate = $report->getEndDate();

            if (!$endDate instanceof \DateTimeInterface) {
                continue;
            }

            $immutableEndDate = \DateTimeImmutable::createFromInterface(
                $endDate
            )->setTime(0, 0);

            if (
                null === $lastReportEndDate
                || $immutableEndDate > $lastReportEndDate
            ) {
                $lastReportEndDate = $immutableEndDate;
            }
        }

        return $lastReportEndDate;
    }

    private function redirectToCollaboratorDetail(
        User $collaborator
    ): Response {
        $url = (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($collaborator->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
