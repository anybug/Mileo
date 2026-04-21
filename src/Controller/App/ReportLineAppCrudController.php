<?php

namespace App\Controller\App;

use App\Controller\App\Filter\LineDateFilter;
use App\Entity\Brand;
use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\Scale;
use App\Entity\UserAddress;
use App\Entity\Vehicule;
use App\Form\FindByMonthType;
use App\Utils\ReportPdf;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRep;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ReportLineAppCrudController extends AbstractCrudController
{
    private $entityManager;
    private $adminUrlGenerator;
    private $cloned;

    public function __construct(EntityManagerInterface $entityManager,AdminUrlGenerator $adminUrlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return ReportLine::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {

        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $queryBuilder->leftJoin('entity.report', 'r');
        $queryBuilder->andWhere('r.user = (:user)');
        $queryBuilder->setParameter('user', $this->getUser());

        return $queryBuilder;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $context = $this->getContext();

        $newResponseParameters = parent::configureResponseParameters($responseParameters);

        $pageName = $newResponseParameters->get('pageName');
        if($pageName == Crud::PAGE_INDEX){
            $newResponseParameters = $this->generateFooterLine($newResponseParameters, $context);
        }

        return $newResponseParameters;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['travel_date' => 'ASC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Mes trajets <br /><span class="fs-6 fw-normal">Mode de saisie <i>trajet par trajet</i>: les trajets saisis ici sont automatiquement regroupés dans un rapport mensuel. <br />Vous pouvez également opter pour le mode de saisie <i>au mois</i> depuis le menu Rapports.</span>')
            ->setPageTitle(Crud::PAGE_NEW, 'Saisir un trajet')
            ->setPageTitle(Crud::PAGE_EDIT, fn (ReportLine $reportLine) => sprintf('Modifier trajet du %s', $reportLine->getTravelDate()->format("d/m/Y")))
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'App/ReportLine/index.html.twig')
            ->overrideTemplate('crud/filters', 'App/ReportLine/filters.html.twig')
            ->setPaginatorPageSize(30)
            ;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody('<script src="https://maps.googleapis.com/maps/api/js?key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&libraries=places"></script>')
        ;
    }

    public function index(AdminContext $context)
    {
        if (!$this->getUser()->getSubscription()->isValid()) {
            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(UserAppCrudController::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        if (!$this->getUser()->hasCompletedSetup()) {
            return $this->redirectToRoute('app', ['menuIndex' => 0, 'submenuIndex' => -1]);
        }

        return parent::index($context);
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        $duplicateAction = Action::new('duplicate', 'Duplicate')
            ->linkToCrudAction('duplicateLine')
            ->setIcon("fa fa-copy")
            ;

        if (count($this->getUser()->getVehicules()) == 0) {
            $actions->remove(Crud::PAGE_INDEX, Action::NEW);
        }

        $actions
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_EDIT, Action::DELETE)
        ;  
        
        return $actions;
    }

    public function edit(AdminContext $context)
    {
        $reportLine = $context->getEntity()->getInstance();
        $currentUser = $this->getUser();

        if ($reportLine->getReport()->getUser() !== $currentUser) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function duplicateLine(AdminContext $context)
    {
        $reportLine = $context->getEntity()->getInstance();
        
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::NEW)
            ->set('sourceId', $reportLine->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    public function createEntity(string $entityFqcn)
    {
        $context = $this->container->get(AdminContextProvider::class)->getContext();
        $request = $context->getRequest();

        $reportId = $request->query->get('reportId');

        $reportLine = new $entityFqcn();
        $reportLine->setVehicule($this->getUser()->getDefaultVehicule());
        $reportLine->setScale($this->getUser()->getDefaultVehicule()->getScale());

        if ($reportId) {
            $report = $this->entityManager->getRepository(Report::class)->find($reportId);

            if ($report && $report->getUser() === $this->getUser()) {
                $reportLine->setTravelDate(\DateTimeImmutable::createFromMutable($report->getStartDate()));
            }
        } else {
            $reportLine->setTravelDate(new \DateTimeImmutable());
        }

        return $reportLine;
    }

    
    public function configureFields(string $pageName): iterable
    {
        $entity = $this->getContext()->getEntity()->getInstance();
        $currentYear = (int) (new \DateTimeImmutable())->format('Y');
        $minYear = $currentYear - 10;
        $maxYear = $currentYear + 1;

        /** @var App\Entity\User */
        $me = $this->getUser();

        $dateFieldHtmlAttributes = [
            'min' => sprintf('%d-01-01', $minYear),
            'max' => sprintf('%d-12-31', $maxYear),
        ];

        if($pageName == Crud::PAGE_EDIT && $entity?->getId())
        {
            $firstDayOfMonth = clone $entity->getTravelDate();
            $firstDayOfMonth->modify('first day of this month');
            $lastDayOfMonth = clone $entity->getTravelDate();
            $lastDayOfMonth->modify('last day of this month');
            $dateFieldHtmlAttributes = ['min' => $firstDayOfMonth->format('Y-m-d'), 'max' => $lastDayOfMonth->format('Y-m-d')];
        }

        yield FormField::addPanel();
        yield DateField::new('travel_date','Date')->setColumns('col-sm-6 col-lg-5 col-xxl-2')->setHtmlAttributes($dateFieldHtmlAttributes)->onlyOnForms();
        yield DateField::new('travel_date','Date')->setFormat('full')->onlyOnIndex();
        yield AssociationField::new('vehicule', 'Véhicule')
            ->setFormTypeOptions([
                'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('v')
                    ->andWhere('v.user = (:user)')
                    ->setParameter('user', $this->getUser());
                },
                'attr' => ['class'=>'report_vehicule']
            ])
            ->setColumns('col-sm-6 col-lg-5 col-xxl-2')
            ->setTemplateName('crud/field/generic')
            ;
        yield FormField::addRow();
        yield FormField::addPanel('Travel information')->setIcon('fa fa-car');

        /** Compte individuel: quelques adresses en bouton radion */
        if(!$me->getManagedBy()){
            $addresses = $this->getUser()->getFormattedUserAddresses();
            yield ChoiceField::new('favories','adresse favorite')
                ->setFormTypeOptions([
                    'attr' => ['class'=>'report_favories'],
                    'expanded' => true,
                    'mapped' => false,
                    'required' => false,
                    'choice_attr' => function($choice, $key, $value) {
                        return ['class' => 'report_favories_choice'];
                    }
                ])
                ->onlyOnForms()
                ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
                ->setChoices(count($addresses)>0 ? $addresses : ["Vous n'avez pas d'adresse favorite" => ""])
            ;
        }else{
            $addresses = $this->getUser()->getFormattedGroupAddresses();
            yield ChoiceField::new('favories','adresse favorite')
                ->setFormTypeOptions([
                    'attr' => ['class'=>'report_favories'],
                    'expanded' => true,
                    'mapped' => false,
                    'required' => false,
                    'choice_attr' => function($choice, $key, $value) {
                        return ['class' => 'report_favories_choice'];
                    }
                ])
                ->onlyOnForms()
                ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
                ->setChoices(count($addresses)>0 ? $addresses : ["Vous n'avez pas d'adresse favorite" => ""])
            ;

        }
        

        /** compte équipe: liste déroulante avec adresses en perso en haut de liste */
        /*yield ChoiceField::new('favories','adresse favorite')
            ->setFormTypeOptions([
                'attr' => ['class'=>'report_favories'],
                'expanded' => true,
                'mapped' => false,
                'required' => true,
                'choice_attr' => function($choice, $key, $value) {
                    return ['class' => 'report_favories_choice'];
                }
            ])
            ->onlyOnForms()
            ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
            ->setChoices(function () {
                $me = $this->getUser();

                if (!($me instanceof \App\Entity\User)) {
                    return ["Vous n'avez pas d'adresse favorite" => ""];
                }

                $myChoices = [];
                $groupChoices = [];

                // --- 1) Mes adresses (EN HAUT) ---
                foreach ($me->getUserAddresses() as $adress) {
                    $label = (string) $adress->getName();
                    $value = (string) $adress->getAddress();

                    $finalLabel = $label;
                    $i = 2;
                    while (isset($myChoices[$finalLabel]) || isset($groupChoices[$finalLabel])) {
                        $finalLabel = $label.' ('.$i++.')';
                    }

                    $myChoices[$finalLabel] = $value;
                }

                // --- 2) Groupe (manager + membres) ---
                $myManager = $me->getManagedBy();
                $qb = $this->entityManager->getRepository(\App\Entity\User::class)->createQueryBuilder('u');

                if ($myManager instanceof \App\Entity\User) {
                    $mgrId = $myManager->getId();
                    $qb->andWhere('IDENTITY(u.managedBy) = :mgrId OR u.id = :mgrId')
                    ->setParameter('mgrId', $mgrId);
                } else {
                    $qb->andWhere('u = :me OR u.managedBy = :me')
                    ->setParameter('me', $me);
                }

                $users = $qb->orderBy('u.id', 'ASC')->getQuery()->getResult();

                foreach ($users as $u) {
                    if ($u->getId() === $me->getId()) {
                        continue; 
                    }

                    foreach ($u->getUserAddresses() as $adress) {
                        $label = (string) $adress->getAddress();
                        $value = (string) $adress->getAddress();

                        $finalLabel = $label;
                        $i = 2;
                        while (isset($groupChoices[$finalLabel]) || isset($myChoices[$finalLabel])) {
                            $finalLabel = $label.' ('.$i++.')';
                        }

                        $groupChoices[$finalLabel] = $value;
                    }
                }

                $choices = $myChoices + $groupChoices;

                return count($choices) ? $choices : ["Vous n'avez pas d'adresse favorite" => ""];
            });
        */


        // --- Départ (FORM) ---
        yield TextField::new('startAdress','Départ')
            ->setFormTypeOptions([
                'attr' => ['class'=>'autocomplete lines_start'],
                'label_html' => true,
                'help' => 'Saisissez une adresse ou <a class="popup-fav-start">selectionnez une de vos <i class="fa fa-map-marker-alt"></i></a>'
            ])
            ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
            ->onlyOnForms();

        // --- Départ (INDEX) ---
        yield TextField::new('startAdress', 'Départ')
            ->onlyOnIndex()
            ->renderAsHtml()
            ->formatValue(fn ($value, $entity) => $entity->formatAddressWithName($value));
       

        // --- Arrivée (FORM) ---
        yield TextField::new('endAdress',"Arrivée")
            ->setFormTypeOptions([
                'attr' => ['class'=>'autocomplete lines_end'],
                'label_html' => true,
                'help' => 'Saisissez une adresse ou <a class="popup-fav-end">selectionnez une de vos <i class="fa fa-map-marker-alt"></i></a>'
            ])
            ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
            ->onlyOnForms();

        // --- Arrivée (INDEX) ---
        yield TextField::new('endAdress', 'Arrivée')
            ->onlyOnIndex()
            ->renderAsHtml()
            ->formatValue(fn ($value, $entity) => $entity->formatAddressWithName($value));


        yield TextareaField::new('comment','Motif du déplacement')
            ->onlyOnIndex()
        ;

        yield HiddenField::new('km','Distance (km)')
            ->setFormTypeOptions(['attr' => ['readonly'=> true, 'class' => 'report_km bg-light not-allowed']])
            ->onlyOnForms()
            //->setColumns('col-sm-4 col-lg-3 col-xxl-2')
            ;
        yield IntegerField::new('km_total','Distance (km)')
            ->setFormTypeOptions(['attr' => ['readonly'=> true, 'class' => 'report_km_total bg-light']])
            ->setColumns('col-sm-4 col-lg-3 col-xxl-2')
            ->hideOnIndex()
            ;
        yield IntegerField::new('km_total','Distance')
            ->onlyOnIndex()
            ->setNumberFormat('%s'.' km')
            ;
        yield FormField::addRow();

        yield BooleanField::new('is_return','Aller retour')
            ->setFormTypeOptions([
            'attr' => ['class'=>'report_is_return']
            ])
            ->onlyOnForms()
            //->renderAsSwitch(false)
            ->setColumns('col-sm-12 col-lg-12 col-xxl-12')
        ;
        yield FormField::addRow();

        yield TextareaField::new('comment','Motif du déplacement')
                ->setFormTypeOptions(['required' => true, 'attr' => ['placeholder' => "Saisissez une courte description qui justifie ce trajet"]])
                ->setColumns('col-12')
                ->onlyOnForms()
        ;
        
        yield FormField::addRow();

        yield FormField::addPanel('Estimation')->setIcon('fa fa-coins');
        /*yield AssociationField::new('scale')
            ->setColumns('col-sm-4 col-lg-3 col-xxl-3')
        ;*/

        yield NumberField::new("amount",'Montant')
            ->setFormTypeOptions(['attr' => ['readonly'=> true,'class'=>'report_amount bg-light', 'help' => "Montant estimé"]])
            ->setColumns('col-sm-4 col-lg-3 col-xxl-2')
            ->onlyOnForms()
        ;
        yield NumberField::new("amount",'Montant')
            ->setNumberFormat('%s'.' €')
            ->onlyOnIndex()
        ;
        
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(LineDateFilter::new('period'))
        ;
    }

    private function getNormalizedPeriod(AdminContext $context, string $filterName): ?array
    {
        $filters = $context->getRequest()->query->all()['filters'][$filterName]['value'] ?? null;

        if (!$filters) {
            return null;
        }

        // Cas simple "12/2025"
        if (is_string($filters)) {
            [$month, $year] = explode('/', $filters);
            return [$month, $year];
        }

        // Cas intervalle : ['start' => '01/12/2025', 'end' => '31/12/2025']
        if (is_array($filters) && isset($filters['start'])) {
            $start = \DateTime::createFromFormat('d/m/Y', $filters['start']);
            return [
                $start->format('m'),
                $start->format('Y')
            ];
        }

        // Cas inattendu : on tente une récupération intelligente
        if (is_array($filters)) {
            $first = reset($filters);
            if (is_string($first) && str_contains($first, '/')) {
                [$month, $year] = explode('/', $first);
                return [$month, $year];
            }
        }

        throw new \Exception("Format de période invalide pour le filtre '$filterName'.");
    }

    public function generateAmountAction(AdminContext $context): JsonResponse
    {
        $request = $context->getRequest();

        $reportId = $request->query->get('report_id');
        $reportLineId = $request->query->get('report_line_id');
        $vehiculeId = $request->query->get('vehicule');
        $distance = $request->query->get('distance');

        $vehicule = $this->entityManager->getRepository(Vehicule::class)->find($vehiculeId);

        if (!$vehicule || !$distance) {
            return new JsonResponse(['amount' => null], 200);
        }

        $scale = null;
        $report = null;

        if ($reportId) {
            $report = $this->entityManager->getRepository(Report::class)->find($reportId);
        } elseif ($reportLineId) {
            $reportLine = $this->entityManager->getRepository(ReportLine::class)->find($reportLineId);

            if ($reportLine) {
                $report = $reportLine->getReport();
            }
        }

        if ($report) {
            foreach ($report->getVehiculesReports() as $vr) {
                if ($vr->getVehicule() == $vehicule) {
                    $scale = $vr->getScale();
                    break;
                }
            }
        }

        if (!$scale) {
            $scale = $vehicule->getScale();
        }

        $previewLine = new ReportLine();
        $previewLine->setScale($scale);
        $previewLine->setKmTotal((float) $distance);
        $previewLine->calculateAmount();

        return new JsonResponse([
            'amount' => number_format($previewLine->getAmount(), 2, '.', '')
        ]);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->getReportForTravel($entityManager, $entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }
    
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        //ne devrait pas servir ici mais just in case
        $this->getReportForTravel($entityManager, $entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function getReportForTravel(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $user = $entityInstance->getVehicule()->getUser();
        $travelDate = $entityInstance->getTravelDate();

        if (!$travelDate) {
            return;
        }

        $report = $user->getReportForTravelDate($travelDate);

        if ($report === null) {
            $report = new Report();
            $report->setUser($user);

            $startMonth = \DateTime::createFromFormat('Y-m-d', $travelDate->format('Y-m-d'));
            $startMonth->modify('first day of this month');

            $endMonth = \DateTime::createFromFormat('Y-m-d', $travelDate->format('Y-m-d'));
            $endMonth->modify('last day of this month');

            $report->setStartDate($startMonth);
            $report->setEndDate($endMonth);

            $entityManager->persist($report);
        }

        $entityInstance->setReport($report);
    }

    public function generatePdfPerMonth(AdminContext $context)
    {
        [$month, $year] = $this->getNormalizedPeriod($context, 'period');

        $report = $this->entityManager
            ->getRepository(Report::class)
            ->findByYearAndMonth($year, $month);

        $pdf = new ReportPdf();
        $pdfContent = $pdf->generatePdf([$report], [$month, $year], 'month');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->generateFilename().'"'
        ]);
    }

    public function generateFooterLine(KeyValueStore $responseParameters, AdminContext $context) 
    {
        $paginator = $responseParameters->get('paginator');
        $lines = $paginator->getResults();
        if (count($lines) != 0) {
            $report = $lines[0]->getReport();
    
            $totals = ['km' => 0, 'amount' => 0];
            $vehiculesTotals = [];
    
            $totals['km'] = $report->getKm();
            $totals['amount'] = $report->getTotal();
    
            foreach ($report->getVehiculesReports() as $line) 
            {
                $vid = $line->getVehicule()->getId();
                if(isset($vehiculesTotals[$vid])){
                    $vehiculesTotals[$vid]['km'] += $line->getKm();
                    $vehiculesTotals[$vid]['amount'] += $line->getTotal();
                }else{
                    $vehiculesTotals[$vid]['Vehicule'] = $line->getVehicule();
                    //$vehiculesTotals[$vid]['Scale'] = $line->getScale();
                    $vehiculesTotals[$vid]['Vr'] = $line;
                    $vehiculesTotals[$vid]['km'] = $line->getKm();
                    $vehiculesTotals[$vid]['amount'] = $line->getTotal();
                }
            }
            
            $parameters = [
                'totals' => $totals,
                'vehiculesTotals' => $vehiculesTotals
            ];
    
            $responseParameters->setAll($parameters);
        }

        return $responseParameters;
    }
    
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        /** @var ReportLine $reportLine */
        $reportLine = $context->getEntity()->getInstance();
        $report = $reportLine->getReport();

        if ($report) {
            $url = $this->adminUrlGenerator
                ->setController(ReportAppCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    #[Route('/admin/report-line/{id}/delete', name: 'admin_report_line_delete', methods: ['POST'])]
    public function deleteReportLineAjax(ReportLine $reportLine, Request $request): JsonResponse
    {
        if ($reportLine->getReport()->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->isCsrfTokenValid('delete' . $reportLine->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 400);
        }

        $this->entityManager->remove($reportLine);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
