<?php

namespace App\Controller\Team;

use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\UserAddress;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRep;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_MANAGER")]
class TeamAddressesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserAddress::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody('<script src="https://maps.googleapis.com/maps/api/js?key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&libraries=places&callback=initAutocomplete"></script>')
        ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere('entity.user = (:user)');
        $qb->setParameter('user', $this->getUser());

        return $qb;
    }

    public function edit(AdminContext $context)
    {
        $address = $context->getEntity()->getInstance();
        $currentUser = $this->getUser();

        if ($address->getUser() !== $currentUser) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }
    
    public function createEntity(string $entityFqcn)
    {
        $vehicule = new UserAddress();
        $vehicule->setUser($this->getUser());
        return $vehicule;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Carnet d\'adresses du groupe <br /><span class="fs-6 fw-normal">Les adresses saisies ici seront accessibles par chacun de vos membres.</span>')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return [
                TextField::new('name', 'Nom'),

                TextField::new('address', 'Adresse'),

                TextField::new('reason', 'Motif'),
            ];
        }

        if (Crud::PAGE_NEW === $pageName || Crud::PAGE_EDIT === $pageName) {
            return [
                FormField::addColumn(6),
                FormField::addFieldset('Informations adresse')->setIcon('fa fa-map-marker-alt'),
                TextField::new('name', 'Nom'),

                TextField::new('address', 'Adresse')
                    ->setFormTypeOptions([
                        'attr' => [
                            'class' => 'autocomplete',
                        ],
                    ]),

                TextareaField::new('reason', 'Motif du déplacement')
                    ->setRequired(false)
                    ->setHelp("Facultatif: ce motif sera automatiquement proposé si cette adresse est utilisée comme destination dans un trajet.")
                    ->setFormTypeOptions([
                        'attr' => [
                            'placeholder' => 'Ex. : Rendez-vous client',
                        ],
                    ]),
            ];
        }

        return [];
    }
}
