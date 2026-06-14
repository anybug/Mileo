<?php

namespace App\Controller\App;

use App\Entity\Power;
use App\Entity\Scale;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Error;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

class VehiculeAppCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Vehicule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $profileUrl = $this->adminUrlGenerator
                    ->setController(UserAppCrudController::class)
                    ->setAction(Action::INDEX)
                    ->setDashboard(DashboardAppController::class)
                    ->generateUrl()
        ;

        $pageIndexTitle = 'Mes véhicules<br /><span class="fs-6 fw-normal">Enregistrez ici vos véhicules et définissez le véhicule qui sera utilisé par défaut pour les IK. Si vous avez plusieurs véhicules, vous pourrez en sélectionner un par trajet.</span>';

        //limite version Pero: 1 seul véhicule
        if(!$this->getUser()->canAddVehicule())
        {
            $pageIndexTitle .= '<br /><span class="fs-6 fw-light small"><i class="fa-solid fa-circle-info"></i> <i>Vous utilisez la version gratuite de Mileo et nous vous en remercions ! Si vous souhaitez ajouter des véhicules supplémentaires, merci de passer à la version Pro depuis <a href="'.$profileUrl.'">votre profil</a></i>.</span>';
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
        $actions
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(function ($entity) {
                    return $entity->getReportlines()->isEmpty() && !$entity->getIsDefault();
                });
            })
            ->remove(Crud::PAGE_INDEX, Action::BATCH_DELETE)
        ;

        //limite version Pero: 1 seul véhicule
        if(!$this->getUser()->canAddVehicule())
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

    public function configureFields(string $pageName): iterable
    {

        if (Crud::PAGE_INDEX === $pageName) {
            yield ChoiceField::new('type', 'Type')->setChoices([
                'Voiture' => 'Car',
                'Moto/Cyclo' => 'Cyclo',
            ]);
            yield AssociationField::new('brand','Marque');
            yield TextField::new('model', 'Modèle');
            yield AssociationField::new('power', 'Puissance Fiscale')->onlyOnIndex();
            yield TextField::new('scale', 'Barème : estimation de la distance annuelle parcourue')->hideOnForm();    
            yield Field::new('hasLatestScale', 'Barème à jour')->onlyOnIndex()->setTemplatePath('App/Fields/boolean.html.twig');  
            yield BooleanField::new('is_electric', 'Ce véhicule est électrique')->setHelp("Le montant des frais de déplacement est majoré de 20 % pour les véhicules électriques.")->renderAsSwitch(Crud::PAGE_INDEX != $pageName);
            yield BooleanField::new('is_default','Véhicule par défaut')->renderAsSwitch(false)->hideOnForm();
            
            return;
        }

        yield FormField::addColumn(6);
        yield FormField::addFieldset('Fiche véhicule et barème')->setIcon('fa fa-car');

        yield ChoiceField::new('type','Type')->setChoices([
            'Voiture' => 'Car',
            'Moto/Cyclo' => 'Cyclo',
        ])
        ->renderAsNativeWidget()
        ->setFormTypeOptions([
            'expanded' => true, 
            'required' => true,
            'choice_attr' => function($choice, $key, $value) {
                return ['class' => 'vehicule_type'];
            },
            'attr' => ['class' => 'd-flex align-items-end gap-4']
        ]);
        yield AssociationField::new('brand','Marque')->setFormTypeOptions(['required' => false, 'attr' => ['data-placeholder' => " ", 'required' => 'required'], 'label_attr' => ['class' => 'required']]);
        yield Field::new('model', 'Modèle');
        yield AssociationField::new('power','Puissance Fiscale')
            ->setFormTypeOptions(['attr' => ['data-placeholder' => " ", 'class' => 'bg-light vehicule_power', 'disabled' => 'disabled'], 'choices' => []]);
        
        yield AssociationField::new('scale', 'Barème : estimation de la distance annuelle parcourue')
            ->setFormTypeOptions([
                'required' => true,
                'attr' => ['data-placeholder' => " ",'class' => 'bg-light vehicule_scale', 'disabled' => 'disabled'], 
                'choices' => [], 
                'help' => "Ce barème sera utilisé pour estimer les indemnités en temps réel, il vous sera demandé de le réajuster lors de l'édition du rapport annuel si besoin. Si vous changez le barème en cours d'année fiscale, vous pourrez l'appliquer aux rapports de toute l'année depuis le module Rapports."
            ])
            ->setRequired(true)
            ->onlyOnForms(); 
        
        yield BooleanField::new('is_electric', 'Ce véhicule est électrique')->setHelp("Le montant des frais de déplacement est majoré de 20 % pour les véhicules électriques.")->renderAsSwitch(Crud::PAGE_INDEX != $pageName);

        yield FormField::addColumn(6);
        yield FormField::addFieldset('Informations complémentaires')->setIcon('fa-regular fa-file-lines');

        yield IntegerField::new('kilometres', 'Kilométrage')->setHelp("Facultatif: indiquez ici le kilométrage du véhicule si vous souhaitez qu'il apparaisse sur les rapports.")->hideOnIndex();

        yield TextField::new('registrationDocumentFile', "Certificat d'immatriculation")
            ->setFormType(VichFileType::class)
            ->onlyOnForms()
            ->setFormTypeOptions([
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'delete_file',
                'download_label' => 'download_file',
                'constraints' => [
                    new File([
                        'maxSize' => '3M',
                        'maxSizeMessage' => 'Le poids du fichier ne doit pas dépasser 3Mo.',
                        'extensions' => [
                            'pdf',
                            'jpg',
                            'jpeg',
                            'png',
                        ],
                        'extensionsMessage' => 'Format de fichier non supporté.',
                    ]),
                ],
            ])
            ->setHelp("Facultatif: permet de sauvegarder ici la carte grise du véhicule (3Mo max.). <br />Formats supportés: pdf, jpg, png.")
        ;

        $helpMore = Crud::PAGE_EDIT ? '<br /><i class="fa-solid fa-circle-info"></i> un véhicule ne pas être supprimé s\'il est défini par défaut ou s\'il a déjà été utilisé dans des rapports.' : '';
        yield BooleanField::new('is_default','Définir comme véhicule par défaut')->onlyOnForms()
                ->setHelp("Si coché, ce véhicule sera sélectionné par défaut lors de la création des trajets.".$helpMore);
                
    }

    public function createEntity(string $entityFqcn)
    {
        $vehicule = new $entityFqcn;
        $vehicule->setUser($this->getUser());
        return $vehicule;
    }

    public function edit(AdminContext $context)
    {
        $vehicule = $context->getEntity()->getInstance();
        $currentUser = $this->getUser();

        if ($vehicule->getUser() !== $currentUser) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function createNewForm(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormInterface
    {
        $builder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        $builder = self::formBuilderModifier($builder, $this->entityManager);
        return $builder->getForm();
    }

    public function createEditForm(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormInterface
    {
        $builder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        $builder = self::formBuilderModifier($builder, $this->entityManager);
        return $builder->getForm();
    }

    static function formBuilderModifier($builder, $em)
    {
        $formModifierType = function (FormInterface $form, $type = null) {
            if($type !== null){
                $form->add('power', EntityType::class, [
                    'required' => true,
                    'class' => Power::class,
                    'query_builder' => function (EntityRepository $er) use($type){
                        return $er->createQueryBuilder('p')
                            ->andWhere('p.type = (:type)')
                            ->setParameter('type', $type);
                        },
                    'attr' => ['class' => 'vehicule_power']
                ]);
            }
        };
        
        $formModifierPower = function (FormInterface $form, Power $power = null) 
        {
            if($power !== null){
                $scales = $power->getLastScale();  

                $form->add('scale', EntityType::class, [
                    'class' => Scale::class,
                    'required' => true,
                    'label' => "Barème : estimation de la distance annuelle parcourue",
                    'choices' => $scales,
                    'attr' => ['class' => 'vehicule_scale'],
                    'help' => "Ce barème sera utilisé pour estimer les indemnités en temps réel, il vous sera demandé de le réajuster lors de l'édition du rapport annuel si besoin. Si vous changez le barème en cours d'année fiscale, vous pourrez l'appliquer aux rapports de toute l'année depuis le module Rapports."
                ]);
            }
            
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifierType, $formModifierPower) {
                $data = $event->getData();
                $formModifierPower($event->getForm(), $data->getPower());
                $formModifierType($event->getForm(), $data->getType());
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formModifierPower, $formModifierType, $em) {
                $data = $event->getData();
                $powerId = array_key_exists('power', $data) ? $data['power'] : false;
                if($powerId){
                    $power = $em->getRepository(Power::class)->find($powerId);
                }else{
                    $power = null;
                }
                
                $type = array_key_exists('type', $data) ? $data['type'] : null;

                $formModifierType($event->getForm(), $type);
                $formModifierPower($event->getForm(), $power);
            }
        );

        /*$builder->get('type')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifierType) {
                $type = $event->getForm()->getData();
                $formModifierType($event->getForm()->getParent(), $type);
            }
        );*/
        
        

        return $builder;
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if(!$entityInstance->getReportlines()->isEmpty() || $entityInstance->getIsDefault()){
            throw new Error('Operation not permitted !');
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

}
