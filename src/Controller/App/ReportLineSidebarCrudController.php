<?php

namespace App\Controller\App;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\UserAddress;
use App\Entity\Vehicule;
use App\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use App\Validator\Constraints\DateRange;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ReportLineSidebarCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminUrlGenerator $adminUrlGenerator,
        private ReportService $reportService,
    ) {}

    public static function getEntityFqcn(): string
    {
        return ReportLine::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addHtmlContentToBody(
            sprintf(
                '<script async defer src="https://maps.googleapis.com/maps/api/js?key=%s&libraries=places"></script>',
                $_ENV['GOOGLE_MAPS_API_KEY']
            )
        );
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Trajet')
            ->setEntityLabelInPlural('Trajets')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un trajet')
            ->setPageTitle(Crud::PAGE_EDIT, fn (ReportLine $line) => sprintf('Modifier le trajet du %s', $line->getTravelDate()?->format('d/m/Y')))
            ->overrideTemplate('layout', 'App/ea_content_only.html.twig')
            ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
            ->overrideTemplate('crud/new', 'App/advanced_new.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::INDEX)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER);
    }

    public function createEntity(string $entityFqcn)
    {
        $request = $this->getContext()->getRequest();
        $reportId = $request->query->getInt('reportId', 0);
        $sourceId = $request->query->getInt('sourceId', 0);

        if ($sourceId > 0) {
            $source = $this->entityManager->getRepository(ReportLine::class)->find($sourceId);

            if (!$source || $source->getReport()?->getUser() !== $this->getUser()) {
                throw new AccessDeniedHttpException();
            }

            $entity = clone $source;
            $entity->resetId();

            if ($reportId > 0) {
                $report = $this->entityManager->getRepository(Report::class)->find($reportId);

                if ($report && $report->getUser() === $this->getUser()) {
                    $entity->setReport($report);
                }
            }

            return $entity;
        }

        $entity = new $entityFqcn();

        $defaultVehicule = $this->getUser()?->getDefaultVehicule();
        if ($defaultVehicule) {
            $entity->setVehicule($defaultVehicule);
            $entity->setScale($defaultVehicule->getScale());
        }

        if ($reportId > 0) {
            $report = $this->entityManager->getRepository(Report::class)->find($reportId);

            if ($report && $report->getUser() === $this->getUser()) {
                $entity->setReport($report);
                $entity->setTravelDate(\DateTimeImmutable::createFromMutable($report->getStartDate()));
            }
        }

        if (!$entity->getTravelDate()) {
            $entity->setTravelDate(new \DateTimeImmutable());
        }

        return $entity;
    }

    public function edit(AdminContext $context)
    {
        /** @var ReportLine $reportLine */
        $reportLine = $context->getEntity()->getInstance();

        if ($reportLine->getReport()?->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function configureFields(string $pageName): iterable
    {
        $currentUser = $this->getUser();
        $addressOwner = $currentUser->getManagedBy() ?? $currentUser;

        $defaultUserAddress = $this->entityManager
            ->getRepository(UserAddress::class)
            ->findOneBy([
                'user' => $addressOwner,
                'is_default' => true,
            ]);

        $defaultStartAddress = trim(
            (string) ($defaultUserAddress?->getAddress() ?? '')
        );

        if ('' === $defaultStartAddress) {
            $defaultStartAddress = null;
        }

        $startAddressHelp = '
            Saisissez une adresse ou
            <a href="#" class="popup-fav-start">
                sélectionnez une de vos <i class="fa fa-map-marker-alt"></i>
            </a>
        ';

        if (null !== $defaultStartAddress) {
            $startAddressHelp .= sprintf(
                '
                <span class="mx-1">ou</span>
                <a
                    href="#"
                    class="js-use-default-start"
                    data-default-address="%s"
                >
                    utilisez votre adresse par défaut
                    <i class="fa-solid fa-house"></i>
                </a>
                ',
                htmlspecialchars(
                    $defaultStartAddress,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                )
            );
        }

        $endAddressHelp = '
            Saisissez une adresse ou
            <a href="#" class="popup-fav-end">
                sélectionnez une de vos <i class="fa fa-map-marker-alt"></i>
            </a>
        ';

        yield FormField::addPanel();

        $reportLine = $this->getContext()->getEntity()->getInstance();
        $report = $reportLine->getReport();

        yield DateField::new('travel_date', 'Date')
            ->onlyOnForms()
            ->setFormTypeOptions([
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new DateRange([
                        'minDate' => $report->getStartDate()->format('Y-m-d'),
                        'maxDate' => $report->getEndDate()->format('Y-m-d'),
                        'message' => 'La date du trajet doit être bornée au mois du rapport.',
                    ]),
                ],
                'attr' => ['min' => $report->getStartDate()->format('Y-m-d'), 'max' => $report->getEndDate()->format('Y-m-d')],
            ]);

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

        yield TextField::new('startAdress', 'Départ')
            ->setFormTypeOptions([
                'attr' => [
                    'class' => 'autocomplete lines_start',
                ],
                'help_html' => true,
                'help' => $startAddressHelp,
            ])
            ->setColumns('col-12 col-md-6')
            ->onlyOnForms();

        yield TextField::new('endAdress', 'Arrivée')
            ->setFormTypeOptions([
                'attr' => [
                    'class' => 'autocomplete lines_end',
                ],
                'help_html' => true,
                'help' => $endAddressHelp,
            ])
            ->setColumns('col-12 col-md-6')
            ->onlyOnForms();

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

        yield BooleanField::new('is_return','Aller retour')
            ->setFormTypeOptions([
            'attr' => ['class'=>'report_is_return']
            ])
            ->onlyOnForms()
            //->renderAsSwitch(false)
            ->setColumns('col-sm-12 col-lg-12 col-xxl-12')
        ;

        yield TextareaField::new('comment', 'Motif du déplacement')
            ->setFormTypeOptions([
                'required' => true,
                'attr' => [
                    'placeholder' => 'Saisissez une courte description qui justifie ce trajet',
                    'class' => 'report_lines_comment',
                ],
            ]);

        yield NumberField::new('amount', 'Montant')
            ->setFormTypeOptions([
                'attr' => ['readonly' => true, 'class' => 'report_amount bg-light'],
            ]);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->attachReportIfNeeded($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof ReportLine && $entityInstance->getReport()) {
            $this->reportService->refreshReport($entityInstance->getReport());
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->attachReportIfNeeded($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof ReportLine && $entityInstance->getReport()) {
            $this->reportService->refreshReport($entityInstance->getReport());
        }
    }

    private function attachReportIfNeeded(ReportLine $entityInstance): void
    {
        if ($entityInstance->getReport()) {
            return;
        }

        $user = $entityInstance->getVehicule()?->getUser();
        if (!$user) {
            return;
        }

        $report = $user->getReportForTravelDate($entityInstance->getTravelDate());

        if ($report === null) {
            $report = new Report();
            $report->setUser($user);

            $startMonth = \DateTime::createFromFormat('Y-m-d', $entityInstance->getTravelDate()->format('Y-m-d'));
            $startMonth->modify('first day of this month');

            $endMonth = \DateTime::createFromFormat('Y-m-d', $entityInstance->getTravelDate()->format('Y-m-d'));
            $endMonth->modify('last day of this month');

            $report->setStartDate($startMonth);
            $report->setEndDate($endMonth);
            $report->addLine($entityInstance);
        } else {
            $entityInstance->setReport($report);
        }
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        /** @var ReportLine $entity */
        $entity = $context->getEntity()->getInstance();
        $report = $entity->getReport();

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(ReportAppCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl()
        );
    }

    public function generateAmountAction(AdminContext $context): JsonResponse
    {
        $report_id = $context->getRequest()->query->get('report_id') ?? false;
        $report_line_id = $context->getRequest()->query->get('report_line_id') ?? false;
        $vehicule_id = $context->getRequest()->query->get('vehicule') ?? false;
        $distance = $context->getRequest()->query->get('distance') ?? false;
        
        $vehicule = $this->entityManager->getRepository(Vehicule::class)->find($vehicule_id);

        $response = new JsonResponse();

        if($report_id){
            $report = $this->entityManager->getRepository(Report::class)->find($report_id);
            foreach ($report->getVehiculesReports() as $vr) {
                if ($vr->getVehicule() == $vehicule) {
                    $scale = $vr->getScale();
                }
            }
        } else if($report_line_id){
            $reportLine = $this->entityManager->getRepository(ReportLine::class)->find($report_line_id);
            $report = $reportLine->getReport();
            foreach ($report->getVehiculesReports() as $vr) {
                if ($vr->getVehicule() == $vehicule) {
                    $scale = $vr->getScale();
                }
            }
        }

        if($vehicule && $distance){

            //vérification si le barême n'est pas déjà attribué pour ce rapport
            if (!isset($scale)) {
                $scale = $vehicule->getScale();
            }

            $reportLine = new ReportLine();
            $reportLine->setScale($scale);
            $reportLine->setKmTotal($distance);    
            $reportLine->calculateAmount();

            $response->setData(['amount' => $reportLine->getAmount()]);
            //die(json_encode($scale->__toString()));  <- pour verif 
        }

        return $response;
    }

    #[Route('/app/report-line/{id}/delete', name: 'app_report_line_delete', methods: ['POST'])]
    public function deleteReportLineAjax(ReportLine $reportLine, Request $request): JsonResponse
    {
        if ($reportLine->getReport()?->getUser()?->getId() !== $this->getUser()?->getId()) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->isCsrfTokenValid('delete' . $reportLine->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 400);
        }

        $report = $reportLine->getReport();

        $this->entityManager->remove($reportLine);
        $this->entityManager->flush();

        if ($report) {
            $this->reportService->refreshReport($report);
        }

        return new JsonResponse(['success' => true]);
    }
}