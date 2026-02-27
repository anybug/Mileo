<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserCrudController extends AbstractCrudController
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }
    
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        $impersonate = Action::new('impersonate', 'Se connecter', 'fa-solid fa-person-walking-arrow-right')
            ->linkToUrl(function (User $user) {
                return $this->generateUrl('app', [
                    '_switch_user' => $user->getEmail(),
                ]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $impersonate)
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield IdField::new('id')->hideOnForm();   
            yield Field::new('first_name', 'Prénom');
            yield Field::new('last_name', 'Nom');
            yield EmailField::new('email', 'E-mail');
            yield DateTimeField::new('last_login', 'Dernière cnx.');
            yield CollectionField::new('reports', 'Nb reports');
            yield Field::new('roles', 'Role(s)')->onlyOnIndex();
            yield BooleanField::new('is_active', 'Profil activé')->setHelp("Si désactivé, l'utilisateur ne peut pas se connecter à la plateforme");
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
                ->setFormTypeOptions([
                    'required' => true,
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => 'Password (confirmation)'],
                    'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ]);
            yield BooleanField::new('is_active', 'Profil activé')->setHelp("Si désactivé, l'utilisateur ne peut pas se connecter à la plateforme");

            return;
        }

    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->encodePassword($entityInstance);
        $entityInstance->setRoles(['ROLE_USER']);
        parent::persistEntity($entityManager, $entityInstance);
    }
    
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
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
    
}
