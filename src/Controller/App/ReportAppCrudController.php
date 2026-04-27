<?php

namespace App\Controller\App;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\Vehicule;
use App\Entity\VehiculesReport;
use App\Form\AssistantAIType;
use App\Service\ReportService;
use App\Form\ReportDuplicateType;
use App\Form\ReportLineType;
use App\Form\ReportTotalScaleType;
use App\Service\MistralApiService;
use App\Service\TripDuplicationService;
use App\Service\XlsxExporter;
use App\Utils\ReportPdf;
use App\Validator\Constraints\NewReport;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ReportAppCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $exporter;
    private $slugger;
    private $mistral;
    private $tripDuplicator;
    private $logger;
    private $dispatcher;
    private $reportService;
    private RequestStack $requestStack;

    public function __construct(
        AdminUrlGenerator $adminUrlGenerator,
        XlsxExporter $exporter,
        SluggerInterface $slugger,
        MistralApiService $mistral,
        TripDuplicationService $tripDuplicator,
        LoggerInterface $logger,
        EventDispatcherInterface $dispatcher,
        ReportService $reportService,
        RequestStack $requestStack
    ) {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->exporter = $exporter;
        $this->slugger = $slugger;
        $this->mistral = $mistral;
        $this->tripDuplicator = $tripDuplicator;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->reportService = $reportService;
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    public function configureResponseParameters(KeyValueStore $parameters): KeyValueStore
    {
        $context = $this->getContext();
        $new = parent::configureResponseParameters($parameters);

        if ($new->get('pageName') === Crud::PAGE_INDEX) {
            $new = $this->generateFooterLine($new, $context);
        }

        return $new;
    }

    public function index(AdminContext $context)
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if (!$user->getSubscription()->isValid()) {
            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(UserAppCrudController::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        if (!$user->hasCompletedSetup()) {
            return $this->redirectToRoute('app', ['menuIndex' => 0, 'submenuIndex' => -1]);
        }

        $sort = $context->getRequest()->query->all('sort');
        $allowedSorts = ['start_date', 'km', 'total', 'end_date'];

        foreach (array_keys($sort) as $field) {
            if (!in_array($field, $allowedSorts, true)) {
                return $this->redirect(
                    $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction(Action::INDEX)
                        ->unset('sort')
                        ->unset('referrer')
                        ->generateUrl()
                );
            }
        }

        return parent::index($context);
    }

    public function createEntity(string $entityFqcn)
    {
        $report = new $entityFqcn();
        $report->setUser($this->getUser());
        return $report;
    }

    public function createIndexQueryBuilder(
        SearchDto $search,
        EntityDto $entity,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($search, $entity, $fields, $filters)
            ->andWhere('entity.user = :user')
            ->setParameter('user', $this->getUser());
    }

    public function configureCrud(Crud $crud): Crud
    {
        $request = $this->requestStack->getCurrentRequest();

        $crudAction = $request?->query->get('crudAction')
            ?? $request?->attributes->get('crudAction')
            ?? $request?->query->get('action')
            ?? $request?->attributes->get('action');

        $crud = $crud
            ->setDefaultSort(['start_date' => 'ASC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Rapports annuels et provisions mensuelles<br /><span class="fs-6 fw-normal">Mode de saisie <i>au mois</i>: chaque rapport contient les trajets effectués le mois concerné. Vous pouvez ajouter/modifier autant de trajets par Rapport que nécessaire, n\'hésitez pas à utiliser l\'assistant pour vous aider.<br />Vous pouvez également opter pour le mode de saisie <i>trajet par trajet</i> depuis le menu Mes trajets.</span>')
            ->setPageTitle(Crud::PAGE_EDIT, fn (Report $r) => sprintf('Modifier le rapport de %s', $r->getPeriod()))
            ->setPageTitle(Crud::PAGE_NEW, 'New report period')
            ->overrideTemplate('crud/index', 'App/Report/index.html.twig')
            ->overrideTemplate('crud/filters', 'App/Report/filters.html.twig')
            ->addFormTheme('App/Report/form_theme.html.twig');

        if (in_array($crudAction, [Crud::PAGE_EDIT, Crud::PAGE_NEW], true)) {
            $crud->setSearchFields(null);
        }

        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        $generatePdf = Action::new('generatePdf')
            ->setIcon("fa fa-file-pdf")
            ->setLabel("PDF")
            ->linkToCrudAction('generatePdf');

        $exportXls = Action::new('exportXls', 'Excel')
            ->linkToCrudAction('exportXls')
            ->setIcon("fa fa-file-excel");
            
        $assistantAI = Action::new('assistant', 'Assistant')
            ->setIcon('fa-solid fa-wand-magic-sparkles')
            ->linkToCrudAction('assistant')
            ->setCssClass('btn btn-secondary')
        ;    

        // Assistant visible si abonnement ≠ FREE -> tout le monde pour l'instant
        /*$subscription = $this->getUser()->getSubscription();
        $planName = $subscription && $subscription->getPlan()
            ? strtoupper((string) $subscription->getPlan()->getName())
            : 'FREE';

        $canSeeAssistant = ($planName !== 'FREE') | $this->isGranted('ROLE_PREVIOUS_ADMIN');

        if ($canSeeAssistant) {
            $actions
                ->add(Crud::PAGE_INDEX, $assistantAI)
                ->add(Crud::PAGE_EDIT, $assistantAI)
            ;
        }*/
        
        $actions
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_RETURN)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_INDEX, Action::BATCH_DELETE)
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE, fn(Action $a) =>
                $a->setIcon("fa-solid fa-arrow-right")
                ->setLabel("Next")
                ->asPrimaryAction()
            )

            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $a) =>
                $a->setCssClass('new-report-action')
                ->asPrimaryAction()
            )
            ->add(Crud::PAGE_INDEX, $generatePdf)
            ->add(Crud::PAGE_INDEX, $exportXls)
			->add(Crud::PAGE_INDEX, $assistantAI)
            ->add(Crud::PAGE_EDIT, $assistantAI)
            ->reorder(Crud::PAGE_INDEX, ['assistant', Action::EDIT, 'generatePdf', 'exportXls', Action::DELETE])
            ->reorder(Crud::PAGE_NEW, [Action::SAVE_AND_CONTINUE, Action::INDEX])
            ->reorder(Crud::PAGE_EDIT, [Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE, 'assistant', Action::INDEX])
        ;

        return $actions;

    }

    public function assistant(AdminContext $context, Request $request): Response
    {
        $report = $context->getEntity()->getInstance();

        if ($report->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $form = $this->createForm(AssistantAIType::class, null, ['report' => $report]);

        // Soumission classique du formulaire
        $form->handleRequest($request);

        $actionUrl = $this->adminUrlGenerator
                ->setController(crudControllerFqcn: self::class)
                ->setAction('assistant')
                ->setEntityId($report->getId())
                ->generateUrl()
            ;
        
        $actionAjaxUrl = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('assistantAjaxForm')
                ->setEntityId($report->getId())
                ->generateUrl()
            ;

        $backUrl = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl()
        ;    
         
        if ($form->isSubmitted() && $form->isValid()) {
           
            $action = $form->get('action')->getData();
            
            switch ($action) {
                case 'duplicate_week':
                    // Exemple : $sourceWeek et $destination sont disponibles
                    $source = $form->get('source_week')->getData();
                    $destination = $form->get('destination')->getData();
                    if(!$this->checkRequiredFields([$action, $source, $destination])){
                        return new JsonResponse(['erreur' => 'Saisie invalide'], 400);
                    }
                    $previewTrips = $this->tripDuplicator->generatePreviewTrips($report, $action, $source, $destination);
                    break;
                case 'duplicate_trip':
                    $source = $form->get('trip_id')->getData();
                    $destination = $form->get('destination')->getData();
                    if(!$this->checkRequiredFields([$action, $source, $destination])){
                        return new JsonResponse(['erreur' => 'Saisie invalide'], 400);
                    }
                    $previewTrips = $this->tripDuplicator->generatePreviewTrips($report, $action, $source, $destination);
                    break;
                case 'duplicate_report':
                    $destination = $form->get('target_period')->getData();
                    $copyMode = $form->get('copy_mode')->getData();
                    if(!$this->checkRequiredFields([$action, $copyMode, $destination])){
                        return new JsonResponse(['erreur' => 'Saisie invalide'], 400);
                    }
                    $previewTrips = $this->tripDuplicator->generatePreviewTrips($report, $action, '', $destination, $copyMode);  
                    break;     
                case '':
                    $previewTrips = [];
                    break;    
            }
			
			
            if ($request->isXmlHttpRequest()) {
					
				$confirmActionUrl = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction('bulkCreateLines')
                    ->setEntityId($report->getId())
                    ->generateUrl()
                ;	
                
				$tplVariables = [
                    'action' => $action,
                    'previewTrips' => $previewTrips,
                    'report' => $report,
                    'confirmActionUrl' => $confirmActionUrl,
                    'backUrl' => $backUrl,
                ];
				
				if ($action === 'duplicate_report') {
					$targetPeriod = $form->get('target_period')->getData();
					$copyMode = $form->get('copy_mode')->getData();

					$tplVariables['confirmActionUrl'] = $this->adminUrlGenerator
						->setController(self::class)
						->setAction('reportDuplication')
						->setEntityId($report->getId())
						->generateUrl();

                    $tplVariables['action'] = $action;
                    $tplVariables['copyMode'] = $copyMode;
                    $tplVariables['targetPeriod'] = $targetPeriod;
				}
				

                return new Response(
                    $this->renderView('App/Report/_assistant_preview_content.html.twig', $tplVariables)
                );
            }

            return new JsonResponse(['error' => 'Formulaire invalide'], 400);

        }    

        return $this->render('App/Report/assistant.html.twig', [
            'form' => $form->createView(),
            'report' => $report,
            'actionUrl' => $actionUrl,
            'backUrl' => $backUrl,
            'actionAjaxUrl' => $actionAjaxUrl
        ]);
    }

    private function checkRequiredFields(array $fields)
    {
        foreach($fields as $requiredField)
        {   
            if(!$requiredField || $requiredField === null || strlen($requiredField)==0){
                return false;
            }
        }

        return true;
    }

    public function assistantAjaxForm(AdminContext $context, Request $request): Response
    {
        $report = $context->getEntity()->getInstance();

        if ($report->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $form = $this->createForm(AssistantAIType::class, null, ['report' => $report]);

        // Soumission classique du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $request->isXmlHttpRequest()) 
        {
            return new Response(
                $this->renderView('App/Report/_assistant_form.html.twig', [
                    'form' => $form->createView(),
                ])
            );
        }

        return $this->render('App/Report/_assistant_form.html.twig', [
            'form' => $form->createView()
        ]);

    }

    public function bulkCreateLines(AdminContext $context): RedirectResponse
    {
        $report = $context->getEntity()->getInstance();
        $request = $context->getRequest();

        $backUrl = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl()
        ;

        $tripsData = json_decode($request->request->get('trips'), true);

        if (empty($tripsData)) {
            $this->addFlash('error', 'Aucun trajet à créer.');
            return $this->redirect($backUrl);
        }

        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);
        $vehicleRepository = $entityManager->getRepository(Vehicule::class);

        foreach ($tripsData as $tripData) {
            $trip = new ReportLine();
            $trip->setTravelDate(new \DateTime($tripData['date']))
                ->setStartAdress($tripData['start'])
                ->setEndAdress($tripData['end'])
                ->setKm($tripData['km'])
                ->setKmTotal($tripData['km_total'])
                ->setIsReturn($tripData['is_return'])
                ->setVehicule($vehicleRepository->find($tripData['vehicule_id']))
                ->setAmount($tripData['amount'])
                ->setComment($tripData['comment'])
                ->setReport($report)
            ;

            $entityManager->persist($trip);
            //$report->addLine($trip);
        }

        $entityManager->flush();

        $this->reportService->refreshReport($report);

        $this->addFlash('success', 'Les trajets ont été crées avec succès.');

        $this->container->get('event_dispatcher')->dispatch(new AfterEntityUpdatedEvent($report));
        
        return $this->redirect($backUrl);
    }

    public function reportDuplication(AdminContext $context): RedirectResponse
    {
        /** @var Report $report */
        $report = $context->getEntity()->getInstance();
        $request = $context->getRequest();

        if ($report->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        // récup depuis POST (recommandé) ou GET
        $targetPeriod = $request->request->get('target_period') ?? $request->query->get('target_period');
        $copyMode = $request->request->get('copy_mode');

        if (!$targetPeriod) {
            $this->addFlash('error', 'Période cible manquante.');
            $url = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl();
            return $this->redirect($url);
        }

        try {
            $newReport = $this->tripDuplicator->duplicateReport($report, $targetPeriod, $copyMode);
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());

            $url = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        $this->addFlash('success', 'Rapport dupliqué avec succès !');

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($newReport->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    public function edit(AdminContext $context)
    {
        if ($context->getEntity()->getInstance()->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function generatePdf(AdminContext $context)
    {
        $report = $context->getEntity()->getInstance();
        $pdf = new ReportPdf();
        $period = [$report->getStartDate()->format('F'), $report->getStartDate()->format('Y')];

        $pdfContent = $pdf->generatePdf([$report], $period, 'month');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->generateFilename().'"'
        ]);
    }

    public function generatePdfPerYear(AdminContext $context)
    {
        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);

        $period = $context->getRequest()->query->all()['filters']["Period"]['value'] ?? false;
        if(!$period)
        {
            throw new \Exception("Période non valide.");
        }

        $period = explode(" -> ",$period);
        $reports = $entityManager->getRepository(Report::class)->findByPeriod($period[0],$period[1]);

        if (!$reports) {
            throw new \Exception("Aucun rapport trouvé pour cette année fiscale.");
        }

        // Génération PDF
        $pdf = new ReportPdf();
        $pdfContent = $pdf->generatePdf($reports,$period,'year');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->generateFilename().'"'
        ]);
    }


    public function scaleChangeForYear(AdminContext $context)
    {
        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);

        $periodFilter = $context->getRequest()->query->all()['filters']["Period"]['value'] ?? false;

        if(!$periodFilter)
        {
            throw new \Exception("Période non valide.");
        }

        [$start, $end] = explode(" -> ",$periodFilter);

        $reports = $entityManager->getRepository(Report::class)->findByPeriod($start,$end);

        if (!$reports) {
            throw new \Exception("Aucun rapport trouvé pour cette année fiscale.");
        }

        // Récupération du VR ciblé
        $vrid = $context->getRequest()->query->get('vrid') ?? false;
        $vehiculesReport = $entityManager->getRepository(VehiculesReport::class)->find($vrid);

        if (!$vehiculesReport) {
            throw new \Exception("Rapport introuvable.");
        }

        // Chargement des choix possibles
        $choices = $this->getChoicesForVehiculeReport($vehiculesReport);

        $form = $this->createForm(ReportTotalScaleType::class, $vehiculesReport, [
            'choices' => $choices
        ]);

        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {

            $vehiculesReport = $form->getData();
            $newScale = $vehiculesReport->getScale();
            $vehicle = $vehiculesReport->getVehicule();
            $reportsToUpdate = [];

            // Mise à jour de tous les rapports de l'année fiscale
            foreach ($reports as $report) 
            {
                foreach ($report->getVehiculesReports() as $vr) 
                {
                    if ($vr->getVehicule() === $vehicle) {
                        $vr->setScale($newScale);
                        $vr->calculateTotal();
                    }
                }

                $entityManager->flush();

                $this->dispatcher->dispatch(new AfterEntityUpdatedEvent($report));
                
            }

            $url = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->set("filters[Period][value]", $periodFilter)
                ->generateUrl()
                ;

            return $this->redirect($url);
        }
    }


    public function generateFooterLine(KeyValueStore $params, AdminContext $context)
    {
        $paginator = $params->get('paginator');
        $reports = $paginator->getResults();

        $totals = ['km' => 0, 'amount' => 0];
        $vehiculesTotals = [];

        foreach ($reports as $report) {
            $totals['km'] += $report->getVehiculesReportsTotalKm();
            $totals['amount'] += $report->getVehiculesReportsTotalAmount();

            foreach ($report->getVehiculesReports() as $vr) {
                $vid = $vr->getVehicule()->getId();

                if (!isset($vehiculesTotals[$vid])) {
                    $vehiculesTotals[$vid] = [
                        'Vehicule' => $vr->getVehicule(),
                        'Scale' => $vr->getScale(),
                        'Vr' => $vr,
                        'km' => 0,
                        'amount' => 0
                    ];
                }

                $vehiculesTotals[$vid]['km'] += $vr->getKm();
                $vehiculesTotals[$vid]['amount'] += $vr->getTotal();
            }
        }

        /* Formulaires mis à jour */
        foreach ($vehiculesTotals as $vid => $data) {

            $vr = $data['Vr'];
            $choices = $this->getChoicesForVehiculeReport($vr);

            $form = $this->createForm(ReportTotalScaleType::class, $vr, [
                'choices' => $choices,
                'action' => $this->adminUrlGenerator
                    ->setAction('scaleChangeForYear')
                    ->set('vrid', $vr->getId())
                    ->generateUrl()
            ]);

            $vehiculesTotals[$vid]['form'] = $form->createView();

            /* Alertes mini + maxi */
            if ($data['Scale']->getKmMax() > '') {
                $kmMax = $data['Scale']->getKmMax();
                $kmMin = $data['Scale']->getKmMin();

                if ($data['km'] >= $kmMax) {
                    $vehiculesTotals[$vid]['warning'] =
                        'La distance totale dépasse celle du barème sélectionné';
                }

                if ($data['km'] <= $kmMin) {
                    $vehiculesTotals[$vid]['info'] =
                        'La distance totale est actuellement en-dessous du seuil du barème sélectionné';
                }
            }
        }

        $params->set('totals', $totals);
        $params->set('vehiculesTotals', $vehiculesTotals);

        return $params;
    }

    public function exportXls(AdminContext $context): Response
    {
        $report = $context->getEntity()->getInstance();

        $rows = [];
        foreach ($report->getLines() as $line) {
            $rows[] = [
                'Véhicule' => $line->getVehicule(),
                'Date' => $line->getTravelDate()->format('d/m/Y'),
                'Départ' => $line->getStartAdress(),
                'Arrivé' => $line->getEndAdress(),
                'Motif' => str_replace('<br />', '\n', $line->getComment()),
                'Distance' => $line->getKmTotal(),
            ];
        }

        $slug = $this->slugger->slug(
            $report->getUser().'_'.$report->getStartDate()->format('m-Y')
        )->lower();

        $fileName = sprintf('Fiche_kilometrique_%s.xlsx', $slug);

        return $this->exporter->export($rows, $fileName, 'xlsx');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(\App\Controller\App\Filter\ReportYearFilter::new('Period'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addRow();

        yield DateField::new('start_date', 'Period')
            ->onlyOnIndex()
            ->setFormat('MMMM y');

        if ($pageName === Crud::PAGE_EDIT) {

            yield DateField::new('start_date', 'Date de début')
                ->setFormTypeOptions(['attr' => ['class' => 'report_start_date']])
                ->onlyOnForms()
                ->hideWhenCreating();

            yield DateField::new('end_date', 'Date de fin')
                ->setFormTypeOptions(['attr' => ['class' => 'report_end_date']])
                ->onlyOnForms()
                ->hideWhenCreating();

            yield Field::new('linesReportList', 'Trajet(s)')
                ->onlyOnForms()
                ->hideWhenCreating()
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('required', false)
                ->setFormTypeOption('block_name', 'linesReportList');
                
        }

        if ($pageName === Crud::PAGE_NEW) {

            yield DateField::new('Year', 'Année')
                ->renderAsChoice()
                ->onlyOnForms()
                ->hideWhenUpdating()
                ->setFormTypeOptions([
                    'required' => true,
                    'years' => range(date('Y') - 4, date('Y') + 1),
                ]);

            yield ChoiceField::new('Period', 'Mois')
                ->setChoices([
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
                    'Décembre' => 'December'
                ])
                ->onlyOnForms()
                ->hideWhenUpdating()
                ->setFormTypeOptions(['required' => true]);
        }

        if ($pageName === Crud::PAGE_INDEX) {
            yield CollectionField::new('lines', 'Trajet(s)')
                ->setTemplatePath('App/Report/lines.html.twig')
                ->onlyOnIndex();
        }

        yield FormField::addRow();

        yield IntegerField::new('km', 'Distance')
            //->setNumberFormat('%s km')
            ->onlyOnIndex();

        yield MoneyField::new('total', 'Montant')
            ->setCurrency('EUR')
            
            ->setStoredAsCents(false)
            ->onlyOnIndex();
    }

    public function delete(AdminContext $context)
    {
        /** @var Report $report */
        $report = $context->getEntity()->getInstance();
        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);
       
        $reportYear = $report->getStartDate()->format('Y');
        $currentYear = (new \DateTime())->format('Y');
        $user = $report->getUser();

        try {
            parent::delete($context);
        } catch (\Exception $e) {
            // gérer l'erreur si la suppression échoue
            throw $e;
        }

        $remainingReports = $entityManager->getRepository(Report::class)
            ->createQueryBuilder('r')
            ->select('count(r.id)')
            ->where('r.user = :user')
            ->andWhere('YEAR(r.start_date) = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $reportYear)
            ->getQuery()
            ->getSingleScalarResult();

        if ($reportYear !== $currentYear && $remainingReports == 0) {
            $targetPeriod = "Jan $currentYear -> Dec $currentYear";

            // On génère l'URL vers l'index de l'année courante
            $url = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->set('filters[Period][value]', $targetPeriod)
                // on supprime le referrer : empêche que EasyAdmin renvoye sur l'ID qui n'existe plus
                ->unset('referrer')
                ->setEntityId(null) 
                ->generateUrl();

            return $this->redirect($url);
        }

        return $this->redirect($this->adminUrlGenerator
            ->setAction(Action::INDEX)
            ->unset('referrer')
            ->generateUrl()
        );
    }

    public function getChoicesForVehiculeReport(VehiculesReport $vehiculesReport)
    {
        $choices = [];

        foreach ($vehiculesReport->getVehicule()->getPower()->getScales() as $scale) {

            // On affiche toutes les versions valides du barème dans l'année fiscale
            if (
                $vehiculesReport->getScale()->getYear() == $scale->getYear()
                || $vehiculesReport->getVehicule()->getScale()->getYear() == $scale->getYear()
            ) {
                $powerLabel = (string) $scale->getPower() . ' (' . $scale->getYear() . ')';
                $choices[$powerLabel][$scale->__toString()] = $scale;
            }
        }

        ksort($choices);
        return $choices;
    }

    public function refreshTotals(AdminContext $context): JsonResponse
    {
        /** @var Report $report */
        $report = $context->getEntity()->getInstance();

        if ($report->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $this->reportService->refreshReport($report);

        return new JsonResponse([
            'trips' => count($report->getLines()),
            'km' => $report->getVehiculesReportsTotalKm(),
            'amount' => number_format($report->getVehiculesReportsTotalAmount(), 2, '.', ''),
        ]);
    }

    private function getReportPeriodFromRequest(Request $request): ?array
    {
        $data = $request->request->all('Report');

        if (!$data) {
            return null;
        }

        $yearData = $data['Year'] ?? null;
        $month = $data['Period'] ?? null;

        $year = is_array($yearData) ? ($yearData['year'] ?? null) : null;

        if (!$year || !$month) {
            return null;
        }

        $startDate = \DateTime::createFromFormat('!F Y', sprintf('%s %s', $month, $year));

        if (!$startDate instanceof \DateTimeInterface) {
            return null;
        }

        $endDate = (clone $startDate)->modify('last day of this month');

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    private function findExistingReport(EntityManagerInterface $entityManager, \DateTimeInterface $startDate): ?Report
    {
        return $entityManager->getRepository(Report::class)->findOneBy([
            'user' => $this->getUser(),
            'start_date' => $startDate,
        ]);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Report) {
            parent::persistEntity($entityManager, $entityInstance);
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $period = $this->getReportPeriodFromRequest($request);

        if (!$period) {
            $this->addFlash('danger', 'La période du rapport est invalide.');
            return;
        }

        $existingReport = $this->findExistingReport($entityManager, $period['startDate']);

        if ($existingReport !== null) {
            $this->addFlash(
                'danger',
                sprintf(
                    'Le rapport pour %s existe déjà.',
                    $period['startDate']->format('m/Y')
                )
            );
            return;
        }

        $entityInstance->setUser($this->getUser());
        $entityInstance->setStartDate($period['startDate']);
        $entityInstance->setEndDate($period['endDate']);

        parent::persistEntity($entityManager, $entityInstance);
    }


    public function new(AdminContext $context): KeyValueStore|Response    
    {
        $request = $context->getRequest();

        if ($request->isMethod('POST')) {
            $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);
            $period = $this->getReportPeriodFromRequest($request);

            if (!$period) {
                $this->addFlash('danger', 'La période du rapport est invalide.');

                return $this->redirect(
                    $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction(Action::NEW)
                        ->unset('entityId')
                        ->unset('referrer')
                        ->generateUrl()
                );
            }

            $existingReport = $this->findExistingReport($entityManager, $period['startDate']);

            if ($existingReport !== null) {
                $this->addFlash(
                    'danger',
                    sprintf(
                        'Le rapport pour %s existe déjà.',
                        $period['startDate']->format('m/Y')
                    )
                );

                return $this->redirect(
                    $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction(Action::NEW)
                        ->unset('entityId')
                        ->unset('referrer')
                        ->generateUrl()
                );
            }
        }

        return parent::new($context);
    }

    public function refreshLines(AdminContext $context): Response
    {
        /** @var Report $report */
        $report = $context->getEntity()->getInstance();

        if ($report->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $this->reportService->refreshReport($report);

        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);
        $entityManager->refresh($report);

        return new Response(
            $this->renderView('App/Report/_lines_readonly_content.html.twig', [
                'report' => $report,
            ])
        );
    }
}
