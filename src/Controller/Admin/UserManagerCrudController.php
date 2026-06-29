<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\PlanCode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use App\Entity\Subscription;
use App\Repository\PlanRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserManagerCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private PlanRepository $planRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityPermission('ROLE_ADMIN')
            ->setPageTitle(Crud::PAGE_INDEX, 'Manager users');
    }

    public function configureActions(Actions $actions): Actions
    {
        $impersonate = Action::new('impersonate', 'Se connecter', 'fa-solid fa-person-walking-arrow-right')
            ->linkToUrl(function (User $user) {
                return $this->generateUrl('app', [
                    '_switch_user' => $user->getEmail(),
                ]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action) => $action
                ->setLabel('View profile')
                ->setIcon('fa-solid fa-eye')
            )
            ->add(Crud::PAGE_INDEX, $impersonate);
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->innerJoin('entity.subscription', 'subscription')
            ->innerJoin('subscription.plan', 'plan')
            ->andWhere('plan.code = :planCode')
            ->setParameter('planCode', PlanCode::TEAM);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield IdField::new('id')->hideOnForm();
            yield Field::new('first_name', 'First name');
            yield Field::new('last_name', 'Last name');
            yield EmailField::new('email', 'E-mail address');
            yield IntegerField::new('monthlyCollaboratorsCount', 'Team members')
                ->setTextAlign('center');
            yield DateTimeField::new('last_login', 'Last login');
            yield BooleanField::new('is_active', 'Profile enabled');

            return;
        }

        if ($pageName === Crud::PAGE_DETAIL) {
            yield FormField::addTab('Profile', 'fa-solid fa-user');

            yield FormField::addColumn('col-12 col-xl-6');

            yield FormField::addFieldset('Personal information')
                ->setIcon('fa-solid fa-id-card');

            yield IdField::new('id');
            yield Field::new('first_name', 'First name');
            yield Field::new('last_name', 'Last name');
            yield Field::new('company', 'Company');
            yield EmailField::new('email', 'E-mail address');
            yield BooleanField::new('is_active', 'Profile enabled');

            yield FormField::addColumn('col-12 col-xl-6');

            yield FormField::addFieldset('Subscription')
                ->setIcon('fa-solid fa-file-invoice-dollar');

            yield DateTimeField::new('subscriptionStartDate', 'Subscription start');
            yield DateTimeField::new('subscriptionEndDate', 'Subscription end');
            yield IntegerField::new('monthlyCollaboratorsCount', 'Active members this month');

            yield FormField::addTab('Summary', 'fa fa-chart-column');
            yield FormField::addColumn(12);

            yield CollectionField::new('members', false)
                ->setTemplatePath('Admin/UserManager/team_members.html.twig');

            return;
        }

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Personal information')
                ->setIcon('fa fa-id-card');

            yield Field::new('first_name', 'First name')
                ->setFormTypeOptions(['required' => true])
                ->setColumns(6);

            yield Field::new('last_name', 'Last name')
                ->setFormTypeOptions(['required' => true])
                ->setColumns(6);

            yield Field::new('company', 'Company');

            yield ChoiceField::new('balanceStartPeriod', 'Balance start period')
                ->setColumns('col-12')
                ->setChoices([
                    'January' => 'January',
                    'February' => 'February',
                    'March' => 'March',
                    'April' => 'April',
                    'May' => 'May',
                    'June' => 'June',
                    'July' => 'July',
                    'August' => 'August',
                    'September' => 'September',
                    'October' => 'October',
                    'November' => 'November',
                    'December' => 'December',
                ]);

            yield FormField::addColumn(6);
            yield FormField::addFieldset('Profile')
                ->setIcon('fa fa-user');

            yield Field::new('email', 'E-mail address')
                ->setHelp('The email address is used as the username to sign in to the platform');

            yield Field::new('plainPassword', 'Password')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'required' => $pageName === Crud::PAGE_NEW,
                    'type' => PasswordType::class,
                    'first_options' => [
                        'label' => 'Password',
                    ],
                    'second_options' => [
                        'label' => 'Password (confirmation)',
                    ],
                    'invalid_message' => 'The passwords do not match',
                ]);

            yield BooleanField::new('is_active', 'Profile enabled')
                ->setHelp('When disabled, the user cannot sign in to the platform');
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        $this->encodePassword($entityInstance);
        $entityInstance->setRoles(['ROLE_MANAGER']);

        $teamPlan = $this->planRepository->findByCode(PlanCode::TEAM);

        if (null === $teamPlan) {
            throw new \LogicException('The TEAM plan could not be found.');
        }

        $subscriptionStart = new \DateTimeImmutable('today');

        $subscription = new Subscription();
        $subscription
            ->setUser($entityInstance)
            ->setPlan($teamPlan)
            ->setSubscriptionStart($subscriptionStart)
            ->setSubscriptionEnd($subscriptionStart->modify('+1 year'));

        $entityInstance->setSubscription($subscription);

        parent::persistEntity($entityManager, $entityInstance);

        $entityManager->persist($subscription);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->encodePassword($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function encodePassword(User $user): void
    {
        if (null !== $user->getPlainPassword()) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $user->getPlainPassword()),
            );
        }
    }
}
