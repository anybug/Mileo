<?php

namespace App\Controller\App;

use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\VehiculeCrudController;
use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\Power;
use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\Scale;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\UserAddress;
use App\Entity\Vehicule;
use App\Form\BugReportType;
use App\Form\UserStep2Type;
use App\Form\UserStep3Type;
use App\Service\ChartService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyAdminFriends\EasyAdminDashboardBundle\Service\EasyAdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Test\FormBuilderInterface;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\UX\Chartjs\Model\Chart;

class DashboardAppController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator, 
        private readonly EasyAdminDashboard $easyAdminDashboard,
        private readonly EntityManagerInterface $entityManager, 
        private readonly FormFactoryInterface $formFactory,
        private readonly Packages $assets,
        private readonly ChartService $chartService,
    )
    {}

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->overrideTemplate('layout', 'App/advanced_layout.html.twig')
            ->setFormThemes(['App/form.html.twig', '@EasyAdmin/crud/form_theme.html.twig'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(100000000)
        ;
    }

    public function configureActions(): Actions
    {

        $actions = parent::configureActions();

        return $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->update(Crud::PAGE_EDIT, Action::INDEX, function (Action $action) {
                return $action->setIcon("fa fa-arrow-left")->setLabel("Retour");
            })

            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->update(Crud::PAGE_NEW, Action::INDEX, function (Action $action) {
                return $action->setIcon("fa fa-arrow-left")->setLabel("Retour");
            })
        ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('app')
        ;
    }

    #[Route(path: '/dashboard', name: 'app')]
    public function index(): Response
    {
        /** Quick & dirty for beta tests */
        /*if ($this->isGranted('ROLE_MANAGER')) {
            return $this->redirectToRoute('manager_dashboard');
        }*/

        $request = $this->container->get('request_stack')->getCurrentRequest();
        $step2 = $request->query->get('step2') ?? false;
        $request->query->get('step3') ?? false;

        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $step = [];

        if (!$user->hasCompletedSetup()) {
            if (!$user->hasCompletedStep2() || $step2) {
                $form = $this->createForm(UserStep2Type::class, $user);
                $step = ['title' => 'Informations personnelles et juridiques', 'number' => 2];
                $step2 = true;
            } else {
                $form = $this->formFactory->createNamed('Vehicule', UserStep3Type::class);
                $step = ['title' => 'Véhicule par défaut', 'number' => 3];
            }

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();

                if ($data instanceof Vehicule) {
                    $data->setUser($user);
                }

                $this->entityManager->persist($data);
                $this->entityManager->flush();

                if (!$step2) {
                    $this->addFlash(
                        'success',
                        '<span class="fs-4">Félicitations! Vous pouvez désormais profiter de Mileo, amusez-vous bien <i class="far fa-smile-wink"></i> </span>'
                    );
                }

                return $this->redirectToRoute('app', [
                    'menuIndex' => 0,
                    'submenuIndex' => '-1',
                ]);
            }

            return $this->render('App/Dashboard/wizard.html.twig', [
                'dashboard' => $this->easyAdminDashboard->getDashboard(),
                'form' => $form->createView(),
                'step' => $step,
            ]);
        }

        $years = $this->getAvailableYears();
        $currentYear = (int) date('Y');

        if (!in_array($currentYear, $years, true)) {
            $years[] = $currentYear;
        }

        rsort($years);

        $yearSelected = (int) ($request?->query->get('yearSelected', $currentYear) ?? $currentYear);

        if (!in_array($yearSelected, $years, true) && count($years) > 0) {
            $yearSelected = $years[0];
        }

        $vehiculeChart = $this->createVehiculesAnnualChart(
            $yearSelected
        );        

        $chartTripsByMonth = $this->createTripsByMonthChart($yearSelected);
        $chartTripsByYear = $this->createTripsByYearChart();

        $chartAmountByMonth = $this->createAmountByMonthChart($yearSelected);
        $chartAmountByYear = $this->createAmountByYearChart();

        $topUsedAddressesChart = $this->createTopUsedAddressesChart($yearSelected);

        $flash = false;
        $url = null;

        foreach ($user->getVehicules() as $vehicule) {
            $url = $this->container->get(AdminUrlGenerator::class)
                ->setController(VehiculeAppCrudController::class)
                ->setAction(Action::INDEX)
                ->set('menuIndex', 6)
                ->generateUrl();

            if (!$vehicule->hasLatestScale()) {
                $flash = true;
            }
        }

        if ($flash && $url !== null) {
            $this->addFlash(
                'info',
                '<span class="fs-4">Certains de vos véhicules ne sont pas configurés avec le dernier barème en date. <a href="'.$url.'" class=""><i class="action-icon fa fa-pen"></i> Mettre à jour mes véhicules</a></span>'
            );
        }

        return $this->render('App/Dashboard/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
            'years' => $years,
            'yearSelected' => $yearSelected,
            'chartTripsByMonth' => $chartTripsByMonth,
            'chartTripsByYear' => $chartTripsByYear,
            'chartAmountByMonth' => $chartAmountByMonth,
            'chartAmountByYear' => $chartAmountByYear,
            'vehiculeChart' => $vehiculeChart,
            'topUsedAddressesChart' => $topUsedAddressesChart,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        //dd($this->assets->getUrl('img/logo.png'));
        return Dashboard::new()
            //->setTitle('Mileo')
            ->setTitle(sprintf('<img src="%s" />', $this->assets->getUrl('img/logo.png')))
            ->setFaviconPath($this->assets->getUrl('img/favicons/favicon.ico'))
            ->disableDarkMode()
            //->renderContentMaximized()
            ;
    }


    public function configureMenuItems(): iterable
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Travels');
        yield MenuItem::linkToCrud('My travels', 'fa-solid fa-map-location-dot', ReportLine::class)->setController(ReportLineAppCrudController::class);
        yield MenuItem::linkToCrud('Monthly reports', 'fa fa-road', Report::class)->setController(ReportAppCrudController::class);

        yield MenuItem::section('Parameters');
        yield MenuItem::linkToCrud('Profile', 'fa fa-id-card', User::class)->setController(UserAppCrudController::class);
        yield MenuItem::linkToCrud('My vehicules', 'fa fa-car', Vehicule::class)->setController(VehiculeAppCrudController::class);
        yield MenuItem::linkToCrud('My addresses', 'fa fa-map-marker-alt', UserAddress::class)->setController(AddressesAppCrudController::class);

        if ($user->hasInvoices()) {
            yield MenuItem::linkToCrud('My invoices', 'fa-solid fa-file-invoice', Order::class)->setController(OrderAppCrudController::class);
        }
        
        yield MenuItem::linkToCrud('Scales', 'fa-solid fa-table', Scale::class)->setController(ScaleAppCrudController::class);

        yield MenuItem::section('Support');
        yield MenuItem::linkToRoute('Contact express', 'fa fa-paper-plane', 'app_contact_express');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $menu = parent::configureUserMenu($user);

        // Nom lisible
        $displayName = method_exists($user, 'getFirstname')
            ? trim(($user->getFirstname() ?? '').' '.($user->getLastname() ?? ''))
            : $user->getUserIdentifier();

        if ($displayName === '') {
            $displayName = $user->getUserIdentifier();
        }

        if ($this->isGranted('IS_IMPERSONATOR')) {
            $menu->setName('Connecté en tant que '.$displayName);
            $menu->displayUserName(true);
        }

        return $menu;
    }

    #[Route('/dashboard/contact-express', name: 'app_contact_express')]
    public function bugReport(Request $request, MailerInterface $mailer): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        $form = $this->createForm(BugReportType::class, [
            'type' => 'suggestion'
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData(); // array

            $type = $data['type'] ?? 'suggestion';
            $category = $data['category'] ?? null;
            $emailUser = '';
            if ($user && method_exists($user, 'getEmail')) {
                $emailUser = (string) $user->getEmail();
            }
            $description = $data['description'] ?? '';

            /** @var UploadedFile|null $file */
            $file = $form->get('screenshot')->getData();

            $subject = sprintf('[Mileo] contact express - %s%s', strtoupper($type), $category ? ' / '.$category : '');

            $email = (new Email())
                ->to($_ENV['ADMIN_EMAIL'])
                ->subject($subject)
                ->text(
                    'Utilisateur : ' . $user . \PHP_EOL  .
                    'E-mail : ' . $emailUser . \PHP_EOL .
                    'Type de demande : ' . $type . \PHP_EOL .
                    'Catégorie (précision) : ' . $category . \PHP_EOL . \PHP_EOL .
                    'Message : ' . \PHP_EOL .
                        $description,
                    'utf-8'
                );

            if ($file) {
                $email->attachFromPath(
                    $file->getPathname(),
                    $file->getClientOriginalName(),
                    $file->getMimeType() ?: 'application/octet-stream'
                );
            }

            $mailer->send($email);

            $this->addFlash('success', 'Merci ! Votre message a bien été envoyé au support, nous faisons notre possible pour le traiter dans les plus bref délais.');
            return $this->redirectToRoute('app');
        }

        return $this->render('App/Dashboard/contact_express.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
            'form' => $form->createView(),
        ]);
    }

    private function getAvailableYears(): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT YEAR(rl.travel_date) AS year')
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->orderBy('year', 'DESC');

        $this->applyCurrentUserFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $row) => (int) $row['year'], $rows);
    }

    private function getTopUsedAddresses(int $year, int $limit = 20): array
    {
        $startAddressQb = $this->entityManager->createQueryBuilder()
            ->select(
                'rl.startAdress AS address',
                'COUNT(rl.id) AS startCount'
            )
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->andWhere('YEAR(rl.travel_date) = :year')
            ->andWhere('rl.startAdress IS NOT NULL')
            ->andWhere('rl.startAdress != :empty')
            ->setParameter('year', $year)
            ->setParameter('empty', '')
            ->groupBy('rl.startAdress');

        $this->applyCurrentUserFilterOnReportLines($startAddressQb);

        $startRows = $startAddressQb->getQuery()->getScalarResult();

        $endAddressQb = $this->entityManager->createQueryBuilder()
            ->select(
                'rl.endAdress AS address',
                'COUNT(rl.id) AS endCount'
            )
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->andWhere('YEAR(rl.travel_date) = :year')
            ->andWhere('rl.endAdress IS NOT NULL')
            ->andWhere('rl.endAdress != :empty')
            ->setParameter('year', $year)
            ->setParameter('empty', '')
            ->groupBy('rl.endAdress');

        $this->applyCurrentUserFilterOnReportLines($endAddressQb);

        $endRows = $endAddressQb->getQuery()->getScalarResult();

        $addresses = [];

        foreach ($startRows as $row) {
            $address = trim((string) $row['address']);

            if ($address === '') {
                continue;
            }

            $key = mb_strtolower($address);

            if (!isset($addresses[$key])) {
                $addresses[$key] = [
                    'address' => $address,
                    'startCount' => 0,
                    'endCount' => 0,
                    'totalCount' => 0,
                ];
            }

            $count = (int) $row['startCount'];

            $addresses[$key]['startCount'] += $count;
            $addresses[$key]['totalCount'] += $count;
        }

        foreach ($endRows as $row) {
            $address = trim((string) $row['address']);

            if ($address === '') {
                continue;
            }

            $key = mb_strtolower($address);

            if (!isset($addresses[$key])) {
                $addresses[$key] = [
                    'address' => $address,
                    'startCount' => 0,
                    'endCount' => 0,
                    'totalCount' => 0,
                ];
            }

            $count = (int) $row['endCount'];

            $addresses[$key]['endCount'] += $count;
            $addresses[$key]['totalCount'] += $count;
        }

        usort($addresses, static function (array $a, array $b): int {
            return $b['totalCount'] <=> $a['totalCount'];
        });

        return array_slice($addresses, 0, $limit);
    }

    private function createVehiculesAnnualChart(int $year): ?Chart
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return null;
        }

        $vehiculeLabels = [];

        foreach ($user->getVehicules() as $vehicule) {
            $vehiculeLabels[$vehicule->getId()] = (string) $vehicule;
        }

        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select(
                'v.id AS vehiculeId',
                'COUNT(rl.id) AS totalTrips',
                'COALESCE(SUM(rl.amount), 0) AS totalAmount'
            )
            ->from(ReportLine::class, 'rl')
            ->innerJoin('rl.vehicule', 'v')
            ->innerJoin('rl.report', 'r')
            ->where('r.user = :user')
            ->andWhere('rl.travel_date IS NOT NULL')
            ->andWhere('YEAR(rl.travel_date) = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->groupBy('v.id')
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return null;
        }

        $labels = [];
        $amountData = [];

        foreach ($rows as $row) {
            $vehiculeId = (int) $row['vehiculeId'];
            $vehiculeName = $vehiculeLabels[$vehiculeId] ?? 'Véhicule';

            // La légende contient uniquement le nom du véhicule.
            $labels[] = $this->chartService->truncateLabel(
                $vehiculeName,
                35
            );

            // La valeur du camembert correspond au montant des IK.
            $amountData[] = round(
                (float) $row['totalAmount'],
                2
            );
        }

        return $this->chartService->createDataChart(
            Chart::TYPE_PIE,
            $labels,
            $amountData,
            sprintf(
                'Montant des IK par véhicule en %d',
                $year
            )
        );
    }

    private function createTopUsedAddressesChart(int $year): ?Chart
    {
        $topUsedAddresses = $this->getTopUsedAddresses($year, 10);

        $labels = array_map(
            fn ($value) => $this->chartService->truncateLabel((string) $value, 35),
            array_column($topUsedAddresses, 'address')
        );

        $data = array_column($topUsedAddresses, 'totalCount');

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_PIE,
            $labels,
            $data,
            $label = sprintf('Top 10 des adresses les plus utilisées en %d', $year)
        );

        return $chart;
    }

    private function createTripsByYearChart(): ?Chart
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('YEAR(rl.travel_date) AS year, COUNT(rl.id) AS total')
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->groupBy('year')
            ->orderBy('year', 'ASC');

        $this->applyCurrentUserFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row['year'];
            $data[] = (int) $row['total'];
        }

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_BAR,
            $labels,
            $data,
            $label = 'Nombre total de trajets par année'
        );

        return $chart;
    }

    private function createTripsByMonthChart(int $year): ?Chart
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('MONTH(rl.travel_date) AS monthNumber, COUNT(rl.id) AS total')
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->andWhere('YEAR(rl.travel_date) = :year')
            ->setParameter('year', $year)
            ->groupBy('monthNumber')
            ->orderBy('monthNumber', 'ASC');

        $this->applyCurrentUserFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $dataByMonth = array_fill(1, 12, 0);

        foreach ($rows as $row) {
            $dataByMonth[(int) $row['monthNumber']] = (int) $row['total'];
        }

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_BAR,
            $labels = ['Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            count(array_filter($dataByMonth)) > 0 ? array_values($dataByMonth) : [],
            $label = sprintf('Nombre de trajets par mois pour l\'année %d', $year)
        );

        return $chart;
    }

    private function createAmountByYearChart(): ?Chart
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('YEAR(r.start_date) AS year, COALESCE(SUM(r.total), 0) AS totalAmount')
            ->from(Report::class, 'r')
            ->where('r.start_date IS NOT NULL')
            ->groupBy('year')
            ->orderBy('year', 'ASC');

        $this->applyCurrentUserFilterOnReports($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row['year'];
            $data[] = (float) $row['totalAmount'];
        }

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_LINE,
            $labels,
            $data,
            $label = 'Indemnités kilométriques totales par année'
        );

        return $chart;
    }

    private function createAmountByMonthChart(int $year): ?Chart
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('MONTH(r.start_date) AS monthNumber, COALESCE(SUM(r.total), 0) AS totalAmount')
            ->from(Report::class, 'r')
            ->where('r.start_date IS NOT NULL')
            ->andWhere('YEAR(r.start_date) = :year')
            ->setParameter('year', $year)
            ->groupBy('monthNumber')
            ->orderBy('monthNumber', 'ASC');

        $this->applyCurrentUserFilterOnReports($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $dataByMonth = array_fill(1, 12, 0);

        foreach ($rows as $row) {
            $dataByMonth[(int) $row['monthNumber']] = (float) $row['totalAmount'];
        }

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_LINE,
            $labels = ['Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            count(array_filter($dataByMonth)) > 0 ? array_values($dataByMonth) : [],
            $label = sprintf('Indemnités kilométriques par mois pour l\'année %d', $year)
        );

        return $chart;
    }

    
    private function applyCurrentUserFilterOnReportLines(QueryBuilder $qb): void
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb
            ->join('rl.report', 'r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user);
    }

    private function applyCurrentUserFilterOnReports(QueryBuilder $qb): void
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb
            ->andWhere('r.user = :user')
            ->setParameter('user', $user);
    }

    #[Route(
        path: '/dashboard/vehicules-chart',
        name: 'app_dashboard_vehicules_chart',
        methods: ['GET']
    )]
    public function vehiculesChart(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $yearSelected = (int) $request->query->get(
            'yearSelected',
            (int) date('Y')
        );

        $vehiculePage = max(
            1,
            (int) $request->query->get('vehiculePage', 1)
        );

        $vehiculeChart = null;
        $vehiculePagination = null;

        if (count($user->getVehicules()) > 1) {
            $result = $this->createTripsAndAmountByVehiculeChart(
                $yearSelected,
                $vehiculePage,
                5
            );

            if ($result !== null) {
                $vehiculeChart = $result['chart'];

                $vehiculePagination = [
                    'page' => $result['page'],
                    'totalPages' => $result['totalPages'],
                    'totalVehicles' => $result['totalVehicles'],
                ];
            }
        }

        return $this->render('App/Dashboard/_vehicule_chart.html.twig', [
            'vehiculeChart' => $vehiculeChart,
            'vehiculePagination' => $vehiculePagination,
            'yearSelected' => $yearSelected,
        ]);
    }
}