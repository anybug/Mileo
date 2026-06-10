<?php

namespace App\Controller\Team;

use Error;
use App\Entity\Power;
use App\Entity\Scale;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
use Vich\UploaderBundle\Form\Type\VichFileType;
use Symfony\Component\Validator\Constraints\File;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
class TeamVehiculeCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return Vehicule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Flotte de véhicules <br /><span class="fs-6 fw-normal">Chaque membre peut se voir attribué un ou plusieurs véhicules.</span>')
            ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
            ->overrideTemplate('crud/new', 'App/advanced_new.html.twig')
            ->setSearchFields(['model', 'user.first_name', 'user.last_name', 'user.email'])
            ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(function (Vehicule $entity) {
                    return $entity->getReportlines()->isEmpty() && !$entity->getIsDefault();
                });
            })
            ->remove(Crud::PAGE_INDEX, Action::BATCH_DELETE)
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
            ->join('entity.user', 'u')
            ->andWhere('u = :me OR u.managedBy = :me')
            ->setParameter('me', $this->getUser());

        return $qb;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield AssociationField::new('user', 'Propriétaire')->setTemplateName('crud/field/generic');
            yield ChoiceField::new('type', 'Type')->setChoices([
                'Voiture' => 'Car',
                'Moto/Cyclo' => 'Cyclo',
            ]);
            yield TextField::new('model', 'Modèle');
            yield AssociationField::new('power', 'Puissance Fiscale')->onlyOnIndex();
            yield TextField::new('scale', 'Barème : estimation de la distance annuelle parcourue')->hideOnForm();    
            yield Field::new('hasLatestScale', 'Barème à jour')->onlyOnIndex()->setTemplatePath('App/Fields/boolean.html.twig');  
            yield BooleanField::new('is_electric', 'Ce véhicule est électrique')->setHelp("Le montant des frais de déplacement est majoré de 20 % pour les véhicules électriques.")->renderAsSwitch(Crud::PAGE_INDEX != $pageName);
        
            return;
        }

        yield AssociationField::new('user', 'Propriétaire')
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class' => User::class,
                'required' => true,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->andWhere('u = :me OR u.managedBy = :me')
                        ->setParameter('me', $this->getUser());
                },
            ])
            ;

        yield ChoiceField::new('type', 'Type')->setChoices([
            'Voiture' => 'Car',
            'Moto/Cyclo' => 'Cyclo',
        ])
            ->renderAsNativeWidget()
            ->setFormTypeOptions([
                'expanded' => true,
                'required' => true,
                'choice_attr' => function () {
                    return ['class' => 'vehicule_type'];
                },
            ]);

        yield AssociationField::new('brand', 'Marque')
            ->setFormTypeOptions([
                'required' => false,
                'attr' => ['data-placeholder' => ' ', 'required' => 'required'],
                'label_attr' => ['class' => 'required'],
            ]);

        yield Field::new('model', 'Modèle');

        yield AssociationField::new('power', 'Puissance Fiscale')
            ->setFormTypeOptions([
                'attr' => ['data-placeholder' => ' ', 'class' => 'bg-light vehicule_power', 'disabled' => 'disabled'],
                'choices' => [],
            ]);

        yield TextField::new('registrationDocumentFile', 'Carte grise')
            ->setFormType(VichFileType::class)
            ->onlyOnForms()
            ->setFormTypeOptions([
                'required' => false,
                'allow_delete' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'maxSizeMessage' => 'Le fichier ne doit pas dépasser 2 Mo.',
                        'extensions' => [
                            'pdf',
                            'jpg',
                            'jpeg',
                            'png',
                        ],
                        'extensionsMessage' => 'Format de fichier non supporté.',
                    ]),
                ],
            ]);

        yield AssociationField::new('scale', 'Barème : estimation de la distance annuelle parcourue')
            ->setFormTypeOptions([
                'required' => true,
                'attr' => ['data-placeholder' => ' ', 'class' => 'bg-light vehicule_scale', 'disabled' => 'disabled'],
                'choices' => [],
                'help' => "Ce barème sera utilisé pour estimer les indemnités en temps réel, il vous sera demandé de le réajuster lors de l'édition du rapport annuel si besoin. Si vous changez le barème en cours d'année fiscale, vous pourrez l'appliquer aux rapports de toute l'année depuis le module Rapports.",
            ])
            ->setRequired(true)
            ->onlyOnForms();

        yield TextField::new('scale', 'Barème : estimation de la distance annuelle parcourue')->hideOnForm();

        yield BooleanField::new('is_electric', 'Ce véhicule est électrique')
            ->setHelp("Le montant des frais de déplacement est majoré de 20 % pour les véhicules électriques.")
            ->renderAsSwitch(Crud::PAGE_INDEX !== $pageName);

        yield IntegerField::new('kilometres', 'Kilométrage')
            ->setHelp("Facultatif: indiquez ici le kilométrage du véhicule si vous souhaitez qu'il apparaisse sur les rapports.")
            ->hideOnIndex();

        if (Crud::PAGE_EDIT === $pageName) {
            yield BooleanField::new('is_default', 'Définir comme véhicule par défaut')
                ->onlyOnForms()
                ->setHelp("Vous ne pouvez pas supprimer un véhicule s'il est défini par défaut ou s'il a déjà été utilisé dans des rapports.");
        } elseif (Crud::PAGE_NEW === $pageName) {
            yield BooleanField::new('is_default', 'Définir comme véhicule par défaut')->onlyOnForms();
        } else {
            yield BooleanField::new('is_default', 'Véhicule par défaut')
                ->renderAsSwitch(false)
                ->hideOnForm();
        }
    }

    public function createEntity(string $entityFqcn)
    {
        $vehicule = new $entityFqcn();
        $vehicule->setUser($this->getUser());
        return $vehicule;
    }

    public function edit(AdminContext $context)
    {
        /** @var Vehicule $vehicule */
        $vehicule = $context->getEntity()->getInstance();

        $me = $this->getUser();
        $owner = $vehicule->getUser();

        if (!$owner || ($owner !== $me && $owner->getManagedBy() !== $me)) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function createNewForm(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormInterface
    {
        $builder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        return self::formBuilderModifier($builder, $this->entityManager)->getForm();
    }

    public function createEditForm(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormInterface
    {
        $builder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        return self::formBuilderModifier($builder, $this->entityManager)->getForm();
    }

    public static function formBuilderModifier($builder, EntityManagerInterface $em)
    {
        $formModifierType = function (FormInterface $form, $type = null) {
            if ($type !== null) {
                $form->add('power', EntityType::class, [
                    'required' => true,
                    'class' => Power::class,
                    'query_builder' => function (EntityRepository $er) use ($type) {
                        return $er->createQueryBuilder('p')
                            ->andWhere('p.type = :type')
                            ->setParameter('type', $type);
                    },
                    'attr' => ['class' => 'vehicule_power'],
                ]);
            }
        };

        $formModifierPower = function (FormInterface $form, Power $power = null) {
            if ($power !== null) {
                $scales = $power->getLastScale();

                $form->add('scale', EntityType::class, [
                    'class' => Scale::class,
                    'required' => true,
                    'label' => 'Barème : estimation de la distance annuelle parcourue',
                    'choices' => $scales,
                    'attr' => ['class' => 'vehicule_scale'],
                    'help' => "Ce barème sera utilisé pour estimer les indemnités en temps réel, il vous sera demandé de le réajuster lors de l'édition du rapport annuel si besoin. Si vous changez le barème en cours d'année fiscale, vous pourrez l'appliquer aux rapports de toute l'année depuis le module Rapports.",
                ]);
            }
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifierType, $formModifierPower) {
                $data = $event->getData();
                $formModifierPower($event->getForm(), $data?->getPower());
                $formModifierType($event->getForm(), $data?->getType());
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formModifierPower, $formModifierType, $em) {
                $data = $event->getData();

                $powerId = is_array($data) && array_key_exists('power', $data) ? $data['power'] : null;
                $power = $powerId ? $em->getRepository(Power::class)->find($powerId) : null;

                $type = is_array($data) && array_key_exists('type', $data) ? $data['type'] : null;

                $formModifierType($event->getForm(), $type);
                $formModifierPower($event->getForm(), $power);
            }
        );

        return $builder;
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Vehicule $entityInstance */
        if (!$entityInstance->getReportlines()->isEmpty() || $entityInstance->getIsDefault()) {
            throw new Error('Operation not permitted !');
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    #[Route('/team/vehicule/ajax/powers', name: 'team_vehicule_ajax_powers', methods: ['GET'])]
    public function ajaxPowers(Request $request): JsonResponse
    {
        $type = $request->query->get('type'); // "Car" | "Cyclo"

        if (!$type) {
            return new JsonResponse(['items' => []]);
        }

        $powers = $this->entityManager->getRepository(Power::class)
            ->createQueryBuilder('p')
            ->andWhere('p.type = :type')
            ->setParameter('type', $type)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $items = array_map(static fn (Power $p) => [
            'id' => (string) $p->getId(),
            'text' => (string) $p, // __toString()
        ], $powers);

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/team/vehicule/ajax/scales', name: 'team_vehicule_ajax_scales', methods: ['GET'])]
    public function ajaxScales(Request $request): JsonResponse
    {
        $powerId = $request->query->get('power');
        if (!$powerId) {
            return new JsonResponse(['items' => []]);
        }

        /** @var Power|null $power */
        $power = $this->entityManager->getRepository(Power::class)->find($powerId);
        if (!$power) {
            return new JsonResponse(['items' => []]);
        }

        // 1) récupérer toutes les scales (Collection<Scale>)
        $scalesAll = $power->getScales();

        if ($scalesAll->isEmpty()) {
            return new JsonResponse(['items' => []]);
        }

        // 2) trouver l'année la plus récente
        $years = array_map(fn(Scale $s) => $s->getYear(), $scalesAll->toArray());
        $lastYear = max($years);

        // 3) filtrer sur l'année la plus récente + trier par km_min
        $scales = array_values(array_filter(
            $scalesAll->toArray(),
            fn(Scale $s) => $s->getYear() === $lastYear
        ));

        usort($scales, fn(Scale $a, Scale $b) => $a->getKmMin() <=> $b->getKmMin());

        // 4) JSON final
        $items = array_map(fn(Scale $s) => [
            'id' => (string) $s->getId(),
            'text' => (string) $s,
        ], $scales);

        return new JsonResponse(['items' => $items]);
    }

}
