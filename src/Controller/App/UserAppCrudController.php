<?php

namespace App\Controller\App;

use App\Controller\App\CalendarUserCrudController;
use App\Dto\CalendarConnectionData;
use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\PlanCode;
use App\Form\CalendarConnectionType;
use App\Form\OrderType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRep;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;


class UserAppCrudController extends AbstractCrudController
{
    private $passwordHasher;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(
        UserPasswordHasherInterface $passwordHasher, 
        AdminUrlGenerator $adminUrlGenerator,
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }
    
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere('entity.id = (:user)');
        $qb->setParameter('user', $this->getUser()->getId());

        return $qb;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
        ->setSearchFields(null)
        ->overrideTemplate('crud/index', 'App/Profile/index.html.twig')
        ->setPageTitle(Crud::PAGE_EDIT, 'Modifier mon profil')
        ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
        ->overrideTemplate('crud/new', 'App/advanced_new.html.twig')
        ->addFormTheme('@EasyAdmin/crud/form_theme.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->disable(Action::NEW)
            ->disable(Action::DELETE);
    }

    public function edit(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();
        $currentUser = $this->getUser();

        if ($user !== $currentUser) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /* should not be called from here */
        $this->encodePassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }
    
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        $this->encodePassword($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }
    
    private function encodePassword(User $user): void
    {
        if ($user->getPlainPassword() != null) {
            $hash = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($hash);
        }
    }
    
    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);
        yield FormField::addFieldset('Informations personnelles')->setIcon('fa fa fa-id-card');
        yield Field::new('first_name')
            ->setFormTypeOptions([
                'required' => true,
                ])
            ->setColumns(6);
        yield Field::new('last_name')
            ->setFormTypeOptions([
                'required' => true,
            ])
            ->setColumns(6);
        yield Field::new('company')
            ;
        yield ChoiceField::new('balanceStartPeriod')
            
            ->setChoices(function (){
                return ['Janvier' => 'January','Février' => 'February','Mars' => 'March','Avril' => 'April','Mai' => "May",'Juin' => 'June','Juillet' => 'July','Août' => 'August','Septembre' => 'September','Octobre' => "October",'Novembre' => 'November','Décembre' => "December"];}
            )
            ->setRequired(true)
            ->setHelp($this->getUser()->getReports() ? 'Attention: vous avez déjà créé des rapports ! Les calculs étant basés sur une année fiscale, modifier la période rendra le montant des rapports annuels passés invalide.' : '')
            ;

        yield FormField::addColumn(6);
        yield FormField::addFieldset('Identifiants')->setIcon('fa fa fa-user');
        if($this->getUser()->getGoogleId())
        {
            yield Field::new('email', 'E-mail address')->setFormTypeOptions([
                'attr' => ['readonly' => true],
                'mapped' => false,
                'data' => $this->getUser()->getUserIdentifier(),
                'help' => 'Vous ne pouvez pas modifier votre adresse e-mail car vous êtes connecté via Google'
            ]);
        }else{
            yield Field::new('email', 'E-mail address');  
        }
        yield Field::new('plainPassword')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'required' =>  false,
                'options' => [
                    'attr' => ['autocomplete' => 'off']
                ],
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Password (confirmation)'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.'
            ]); 
    }
    
    public function subscriptionForm(Request $request, EntityManagerInterface $manager)
    {
        $order = new Order;
        $plan = $manager->getRepository(Plan::class)->findByCode(PlanCode::PRO);
        $order->setPlan($plan);

        //order name autocomplete
        $order->setBillingName($this->getUser()->getCompany() ?? $this->getUser()->__toString()); 
        $order->setUser($this->getUser());
        $order->setPlan($plan);
        $order->setProductName($plan->getName());
        $order->setProductDescription($plan->getBillingDetails());
        $order->setTotalHt($plan->getPricePerYear());
        $order->calculateVatAmount();
        $order->setStatus('new');
        
        //TODO: autocomplete billingAddress, billingPostcode, billingCity

        $form = $this->createForm(OrderType::class, $order);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $order->setStatus('pending');
            $manager->persist($order);
            $manager->flush();

            return $this->redirectToRoute('payum_prepare_payment', ['order_id' => $order->getId()]);
        }

        return $this->render('App/Profile/order.html.twig', [
            'form' => $form,
            'plan' => $plan
        ]);
    }

    public function requestDeleteMe(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrf
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }

        // CSRF (simple)
        $submittedToken = (string) $request->request->get('_token');
        if (!$csrf->isTokenValid(new CsrfToken('delete_me', $submittedToken))) {
            throw new AccessDeniedException('CSRF invalide');
        }

        // Génère / remplace un token (valable 24h par ex.)
        $token = bin2hex(random_bytes(32));
        $user->setDeleteToken($token);
        $user->setDeleteTokenRequestedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $confirmUrl = $urlGenerator->generate(
            'app_confirm_delete_me',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->bcc($_ENV['ADMIN_EMAIL'])
            ->subject('Demande de suppression de votre compte Mileo')
            ->htmlTemplate('Emails/delete_account_confirm.html.twig')
            ->context([
                'user' => $user,
                'confirmUrl' => $confirmUrl,
                'validHours' => 24,
            ]);

        $mailer->send($email);

        $this->addFlash('info', 'Un email de confirmation de vient de vous être envoyé.');
        return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->setEntityId($user->getId())->generateUrl());
    }

    #[Route('/dashboard/delete-account/confirm/{token}', name: 'app_confirm_delete_me', methods: ['GET'])]
    public function confirmDeleteMe(
        string $token,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        MailerInterface $mailer
    ): Response {
        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['deleteToken' => $token]);

        if (!$user || !$user->getDeleteTokenRequestedAt()) {
            return new Response('Lien invalide.', 404);
        }

        // Expiration 24h
        $expiresAt = $user->getDeleteTokenRequestedAt()->modify('+24 hours');
        if (new \DateTimeImmutable() > $expiresAt) {
            return new Response('Lien expiré.', 410);
        }

        // IMPORTANT: détacher les orders AVANT suppression user
        foreach ($user->getOrders() as $order) {
            $order->setUser(null);
            $em->persist($order);
        }

        // Nettoyage token
        $user->setDeleteToken(null);
        $user->setDeleteTokenRequestedAt(null);

        $userEmail = (string) $user->getEmail();

        $mailer->send(
            (new TemplatedEmail())
                ->bcc($_ENV['ADMIN_EMAIL'])
                ->to($userEmail)
                ->subject('Votre compte Mileo a bien été supprimé')
                ->htmlTemplate('Emails/account_deleted.html.twig')
                ->context([
                    'user' => $user,
                    'userEmail' => $userEmail,
                ])
        );

        // Suppression user (cascade sur vehicules/reports etc, mais PAS orders)
        $em->remove($user);
        $em->flush();

        $tokenStorage->setToken(null);
        $session->invalidate();

        $this->addFlash('info', 'Votre compte Mileo a bien été supprimé.');

        return $this->redirectToRoute('security_login');
    }

    public function configureResponseParameters(KeyValueStore $parameters): KeyValueStore
    {
        $parameters = parent::configureResponseParameters($parameters);

        if ($parameters->get('pageName') !== Crud::PAGE_INDEX) {
            return $parameters;
        }

        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $parameters;
        }

        $calendarData = new CalendarConnectionData();
        $calendarData->calendarUrl = $user->getCalendarUrl();
        $calendarData->calendarUsername = $user->getCalendarUsername();

        $calendarValidationUrl = $this->adminUrlGenerator
            ->setController(CalendarUserCrudController::class)
            ->setAction('validateCalendarUrl')
            ->setEntityId($user->getId())
            ->generateUrl();

        $calendarForm = $this->createForm(CalendarConnectionType::class, $calendarData, [
            'show_url' => true,
            'calendar_validation_url' => $calendarValidationUrl,
            'has_saved_password' => !empty($user->getCalendarEncryptedPassword()),
            'credentials_required' => false,
        ]);

        $parameters->set('calendarForm', $calendarForm->createView());
        $parameters->set('calendarValidationUrl', $calendarValidationUrl);
        $parameters->set('hasSavedCalendar', !empty($user->getCalendarUrl()));

        return $parameters;
    }
}
