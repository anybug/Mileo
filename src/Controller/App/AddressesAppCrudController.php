<?php

namespace App\Controller\App;

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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRep;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AddressesAppCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {}

    public static function getEntityFqcn(): string
    {
        return UserAddress::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody('<script src="https://maps.googleapis.com/maps/api/js?key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&libraries=places"></script>')
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $profileUrl = $this->adminUrlGenerator
                    ->setController(UserAppCrudController::class)
                    ->setAction(Action::INDEX)
                    ->setDashboard(DashboardAppController::class)
                    ->generateUrl()
        ;

        $pageIndexTitle = 'Mes adresses<br /><span class="fs-6 fw-normal">Gagnez encore plus de temps en enregistrant ici vos adresses récurrentes ! Lors de la saisie de vos trajets, vous pourrez utiliser votre carnet d\'adresses favorites en 2 clics.</span>';

        //limite version Perso: 3 adresses favorites
        if(!$this->getUser()->canAddAddress())
        {
            $pageIndexTitle .= '<br /><span class="fs-6 fw-light"><i class="fa-solid fa-circle-info"></i> <i>Vous utilisez la version gratuite de Mileo et nous vous en remercions! Si vous souhaitez ajouter des adresses récurrentes supplémentaires, merci de passer à la version Pro depuis <a href="'.$profileUrl.'">votre profil</a></i>.</span>';
        }
        
        $crud
            ->setPageTitle(Crud::PAGE_INDEX, $pageIndexTitle)
            ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
            ->overrideTemplate('crud/new', 'App/advanced_new.html.twig')
        ;

        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        //limite version Perso: 3 adresses récurrentes
        if(!$this->getUser()->canAddAddress())
        {
            $actions->disable(Action::NEW);
        }

        return $actions;
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


    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name','Nom'),
            TextField::new('address','Adresse')
                ->setFormTypeOptions(['attr' => ['class'=>'autocomplete']])
            ,
        ];
    }
}
