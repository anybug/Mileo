<?php

namespace App\Controller\App;

use App\Dto\CalendarConnectionData;
use App\Entity\User;
use App\Form\CalendarConnectionType;
use App\Service\CalendarReportImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CalendarUserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly CalendarReportImporter $calendarReportImporter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(null)
            ->setPageTitle(Crud::PAGE_INDEX, 'Mon calendrier')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier mon calendrier')
            ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
            ->addFormTheme('@EasyAdmin/crud/form_theme.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->disable(Action::DELETE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_EDIT, Action::INDEX, function (Action $action) {
                return $action
                    ->setLabel('Retour')
                    ->linkToUrl($this->getProfileUrl());
            })
        ;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $qb
            ->andWhere('entity.id = :user')
            ->setParameter('user', $this->getUser()->getId());

        return $qb;
    }

    public function edit(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();

        if ($user !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    private function getProfileUrl(): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setDashboard(DashboardAppController::class)
            ->setController(UserAppCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        return $this->redirect($this->getProfileUrl());
    }

    public function configureFields(string $pageName): iterable
    {
        $context = $this->getContext();

        /** @var User|null $editedUser */
        $editedUser = $context?->getEntity()?->getInstance();

        /** @var User $user */
        $user = $editedUser instanceof User ? $editedUser : $this->getUser();

        $calendarValidationUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('validateCalendarUrl')
            ->setEntityId($user->getId())
            ->generateUrl();

        yield FormField::addFieldset('Synchronisation du calendrier')
            ->setIcon('fa fa-calendar-days');

        yield BooleanField::new('calendarSynchronized', 'Calendrier synchronisé')
            ->setHelp('Indique si l’URL du calendrier a été validée avec succès.')
            ->renderAsSwitch(false)
            ->hideOnForm();

        yield $this->getCalendarConnectionField($user, $calendarValidationUrl);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            if (!$this->applyCalendarConnectionData($entityInstance)) {
                return;
            }

            $this->encodeCalendarPassword($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
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

    public function validateCalendarUrl(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            /** @var User|null $currentUser */
            $currentUser = $this->getUser();

            if (!$currentUser instanceof User) {
                return new JsonResponse([
                    'valid' => false,
                    'auth_required' => false,
                    'message' => 'Utilisateur non connecté.',
                ], 403);
            }

            $entityId = $request->query->get('entityId');

            /** @var User|null $user */
            $user = $entityId
                ? $entityManager->getRepository(User::class)->find($entityId)
                : $currentUser;

            if (!$user instanceof User) {
                return new JsonResponse([
                    'valid' => false,
                    'auth_required' => false,
                    'message' => 'Utilisateur introuvable.',
                ], 404);
            }

            $canEditOwnCalendar = $user->getId() === $currentUser->getId();
            $canEditManagedUserCalendar = $user->getManagedBy()?->getId() === $currentUser->getId();

            if (!$canEditOwnCalendar && !$canEditManagedUserCalendar) {
                return new JsonResponse([
                    'valid' => false,
                    'auth_required' => false,
                    'message' => 'Vous ne pouvez pas modifier ce calendrier.',
                ], 403);
            }

            $withCredentials = $request->request->getBoolean('withCredentials');
            $saveCalendar = $request->request->getBoolean('saveCalendar');

            $calendarUrl = trim((string) $request->request->get('calendarUrl', ''));
            $calendarUsername = trim((string) $request->request->get('calendarUsername', ''));
            $plainCalendarPassword = trim((string) $request->request->get('plainCalendarPassword', ''));

            if ($calendarUrl === '') {
                return new JsonResponse([
                    'valid' => false,
                    'auth_required' => false,
                    'message' => 'Veuillez renseigner une URL de calendrier.',
                ], 400);
            }

            /*
            * Étape 1 : validation de l’URL seule.
            * Le JS appelle ce cas quand on clique sur "Valider l’URL".
            */
            if (!$withCredentials) {
                $result = $this->calendarReportImporter->testCalendarUrl(
                    $calendarUrl,
                    null,
                    null
                );

                if (($result['auth_required'] ?? false) === true) {
                    return new JsonResponse([
                        'valid' => false,
                        'auth_required' => true,
                        'message' => $result['message'] ?? 'URL reconnue. Ce calendrier nécessite une authentification.',
                    ]);
                }

                if (($result['valid'] ?? false) === true) {
                    if ($saveCalendar) {
                        $user->setCalendarUrl($calendarUrl);
                        $user->setCalendarUsername(null);
                        $user->setPlainCalendarPassword(null);
                        $user->setCalendarEncryptedPassword(null);
                        $user->setCalendarSynchronized(true);

                        $entityManager->flush();
                    }

                    return new JsonResponse([
                        'valid' => true,
                        'auth_required' => false,
                        'message' => $result['message'] ?? 'URL du calendrier validée. Aucun identifiant n’est nécessaire.',
                    ]);
                }

                return new JsonResponse([
                    'valid' => false,
                    'auth_required' => false,
                    'message' => $result['message'] ?? 'URL de calendrier invalide.',
                ], 400);
            }

            /*
            * Étape 2 : validation avec identifiants.
            * Le JS appelle ce cas quand l’URL nécessite une authentification.
            */
            if ($calendarUsername === '' || $plainCalendarPassword === '') {
                return new JsonResponse([
                    'valid' => false,
                    'auth_required' => true,
                    'message' => 'Veuillez renseigner l’identifiant et le mot de passe d’application.',
                ], 400);
            }

            $result = $this->calendarReportImporter->testCalendarUrl(
                $calendarUrl,
                $calendarUsername,
                $plainCalendarPassword
            );

            if (($result['valid'] ?? false) === true) {
                if ($saveCalendar) {
                    $user->setCalendarUrl($calendarUrl);
                    $user->setCalendarUsername($calendarUsername);
                    $user->setCalendarEncryptedPassword($plainCalendarPassword);
                    $user->setPlainCalendarPassword(null);
                    $user->setCalendarSynchronized(true);

                    $entityManager->flush();
                }

                return new JsonResponse([
                    'valid' => true,
                    'auth_required' => true,
                    'message' => $result['message'] ?? 'Identifiants validés.',
                ]);
            }

            return new JsonResponse([
                'valid' => false,
                'auth_required' => true,
                'message' => $result['message'] ?? 'Identifiant ou mot de passe incorrect.',
            ], 400);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'valid' => false,
                'auth_required' => false,
                'message' => 'Erreur serveur pendant la validation du calendrier : ' . $e->getMessage(),
            ], 500);
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
                'credentials_required' => false,
                'has_saved_calendar' => !empty($user->getCalendarUrl()),
            ])
            ->setColumns(12);
    }

    private function applyCalendarConnectionData(User $user): bool
    {
        $request = $this->getContext()?->getRequest();

        if (!$request) {
            return true;
        }

        $calendarConnection = null;

        foreach ($request->request->all() as $formData) {
            if (!is_array($formData)) {
                continue;
            }

            if (isset($formData['calendarConnection']) && is_array($formData['calendarConnection'])) {
                $calendarConnection = $formData['calendarConnection'];
                break;
            }
        }

        if (!is_array($calendarConnection)) {
            return true;
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

            return true;
        }

        $result = $this->calendarReportImporter->testCalendarUrl(
            $calendarUrl,
            null,
            null
        );

        if (($result['auth_required'] ?? false) === true) {
            $passwordToTest = $plainCalendarPassword ?: $user->getCalendarEncryptedPassword();

            if ($calendarUsername === '' || !$passwordToTest) {
                $this->addFlash(
                    'danger',
                    'Ce calendrier nécessite un identifiant et un mot de passe d’application.'
                );

                return false;
            }

            $result = $this->calendarReportImporter->testCalendarUrl(
                $calendarUrl,
                $calendarUsername,
                $passwordToTest
            );
        }

        if (($result['valid'] ?? false) !== true) {
            $this->addFlash(
                'danger',
                $result['message'] ?? 'L’URL du calendrier n’a pas pu être validée.'
            );

            return false;
        }

        $user->setCalendarUrl($calendarUrl);
        $user->setCalendarUsername($calendarUsername !== '' ? $calendarUsername : null);

        if ($plainCalendarPassword !== '') {
            $user->setPlainCalendarPassword($plainCalendarPassword);
        }

        $user->setCalendarSynchronized(true);

        return true;
    }

    #[Route('/dashboard/calendar/disable', name: 'app_calendar_disable', methods: ['POST'])]
    public function disableCalendar(
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non connecté.',
            ], 403);
        }

        $entityId = $request->query->get('entityId');

        /** @var User|null $user */
        $user = $entityId
            ? $entityManager->getRepository(User::class)->find($entityId)
            : $currentUser;

        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $canEditOwnCalendar = $user->getId() === $currentUser->getId();
        $canEditManagedUserCalendar = $user->getManagedBy()?->getId() === $currentUser->getId();

        if (!$canEditOwnCalendar && !$canEditManagedUserCalendar) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous ne pouvez pas modifier ce calendrier.',
            ], 403);
        }

        $submittedToken = (string) $request->request->get('_token');

        if (!$csrfTokenManager->isTokenValid(
            new CsrfToken('disable_calendar_' . $user->getId(), $submittedToken)
        )) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Jeton de sécurité invalide.',
            ], 403);
        }

        $user->setCalendarUrl(null);
        $user->setCalendarUsername(null);
        $user->setPlainCalendarPassword(null);
        $user->setCalendarEncryptedPassword(null);
        $user->setCalendarSynchronized(false);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'La synchronisation du calendrier a été désactivée.',
        ]);
    }
}