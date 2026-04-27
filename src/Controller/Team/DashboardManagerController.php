<?php

namespace App\Controller\Team;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\User;
use App\Entity\UserAddress;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyAdminFriends\EasyAdminDashboardBundle\Service\EasyAdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[IsGranted("ROLE_MANAGER")]
#[AdminDashboard(routePath: '/manager', routeName: 'manager_dashboard')]
class DashboardManagerController extends AbstractDashboardController
{
    private Packages $assets;
    private EasyAdminDashboard $easyAdminDashboard;

    public function __construct(
        Packages $packages,
        EasyAdminDashboard $easyAdminDashboard,
        private readonly RequestStack $requestStack,
        private readonly ChartBuilderInterface $chartBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->assets = $packages;
        $this->easyAdminDashboard = $easyAdminDashboard;
    }

    public function index(): Response
    {
        $request = $this->requestStack->getCurrentRequest();

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

        $chartTripsByMonth = $this->createTripsByMonthChart($yearSelected);
        $chartTripsByYear = $this->createTripsByYearChart();

        $chartAmountByMonth = $this->createAmountByMonthChart($yearSelected);
        $chartAmountByYear = $this->createAmountByYearChart();

        return $this->render('Team/Dashboard/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
            'years' => $years,
            'yearSelected' => $yearSelected,
            'chartTripsByMonth' => $chartTripsByMonth,
            'chartTripsByYear' => $chartTripsByYear,
            'chartAmountByMonth' => $chartAmountByMonth,
            'chartAmountByYear' => $chartAmountByYear,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle(sprintf('<img src="%s" />', $this->assets->getUrl('img/logo.png')))
            ->setFaviconPath($this->assets->getUrl('img/favicons/favicon.ico'))
            ->disableDarkMode();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('app');
    }

    public function configureActions(): Actions
    {
        $actions = parent::configureActions();

        return $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->update(Crud::PAGE_EDIT, Action::INDEX, fn (Action $action) => $action->setIcon('fa fa-arrow-left')->setLabel('Retour'))
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->update(Crud::PAGE_NEW, Action::INDEX, fn (Action $action) => $action->setIcon('fa fa-arrow-left')->setLabel('Retour'));
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->showEntityActionsInlined()
            ->overrideTemplate('layout', 'Team/advanced_layout.html.twig');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Équipe');
        yield MenuItem::linkToCrud('Membres collaborateurs', 'fa fa-users', User::class)
            ->setController(TeamUserCrudController::class);
        yield MenuItem::linkToCrud('Flotte de véhicules', 'fa fa-car', Vehicule::class)
            ->setController(TeamVehiculeCrudController::class);
        yield MenuItem::linkToCrud('Carnet d\'adresses', 'fa fa-map-marker-alt', UserAddress::class)
            ->setController(TeamAddressesCrudController::class);

        yield MenuItem::section('Parameters');
        yield MenuItem::linkToCrud('Profile', 'fa fa-id-card', User::class)
            ->setController(ManagerProfileCrudController::class);
    }

    private function getAvailableYears(): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT YEAR(rl.travel_date) AS year')
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->orderBy('year', 'DESC');

        $this->applyManagedUsersFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        return array_map(static fn(array $row) => (int) $row['year'], $rows);
    }

    private function createTripsByYearChart(): ?Chart
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('YEAR(rl.travel_date) AS year, COUNT(rl.id) AS total')
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->groupBy('year')
            ->orderBy('year', 'ASC');

        $this->applyManagedUsersFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row['year'];
            $data[] = (int) $row['total'];
        }

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Nombre de trajets par année',
                'data' => $data,
                'backgroundColor' => 'rgba(13,110,253,0.15)',
                'borderColor' => 'rgb(13,110,253)',
                'pointBackgroundColor' => 'rgb(13,110,253)',
                'fill' => true,
                'tension' => 0.3,
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => false,
                    'text' => sprintf('Nombre total de trajets par année'),
                ],
            ],
        ]);

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

        $this->applyManagedUsersFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $dataByMonth = array_fill(1, 12, 0);
        foreach ($rows as $row) {
            $dataByMonth[(int) $row['monthNumber']] = (int) $row['total'];
        }

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            'datasets' => [[
                'label' => sprintf('Nombre de trajets des collaborateurs en %d', $year),
                'data' => array_values($dataByMonth),
                'backgroundColor' => [
                    'rgba(54, 162, 235, 0.75)',
                    'rgba(75, 192, 192, 0.75)',
                    'rgba(153, 102, 255, 0.75)',
                    'rgba(255, 159, 64, 0.75)',
                    'rgba(255, 99, 132, 0.75)',
                    'rgba(255, 205, 86, 0.75)',
                    'rgba(54, 162, 235, 0.75)',
                    'rgba(75, 192, 192, 0.75)',
                    'rgba(153, 102, 255, 0.75)',
                    'rgba(255, 159, 64, 0.75)',
                    'rgba(255, 99, 132, 0.75)',
                    'rgba(255, 205, 86, 0.75)',
                ],
                'borderColor' => [
                    'rgb(54, 162, 235)',
                    'rgb(75, 192, 192)',
                    'rgb(153, 102, 255)',
                    'rgb(255, 159, 64)',
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)',
                    'rgb(54, 162, 235)',
                    'rgb(75, 192, 192)',
                    'rgb(153, 102, 255)',
                    'rgb(255, 159, 64)',
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)',
                ],
                'hoverBackgroundColor' => [
                    'rgba(54, 162, 235, 0.9)',
                    'rgba(75, 192, 192, 0.9)',
                    'rgba(153, 102, 255, 0.9)',
                    'rgba(255, 159, 64, 0.9)',
                    'rgba(255, 99, 132, 0.9)',
                    'rgba(255, 205, 86, 0.9)',
                    'rgba(54, 162, 235, 0.9)',
                    'rgba(75, 192, 192, 0.9)',
                    'rgba(153, 102, 255, 0.9)',
                    'rgba(255, 159, 64, 0.9)',
                    'rgba(255, 99, 132, 0.9)',
                    'rgba(255, 205, 86, 0.9)',
                ],
                'borderWidth' => 1,
                'borderRadius' => 6,
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => false,
                    'text' => sprintf('Trajets des collaborateurs par mois - %d', $year),
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ]);

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

        $this->applyManagedUsersFilterOnReports($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row['year'];
            $data[] = (float) $row['totalAmount'];
        }

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Indemnité kilométrique des collaborateurs par année',
                'backgroundColor' => 'rgba(40, 167, 69, 0.2)',
                'borderColor' => 'rgb(40, 167, 69)',
                'pointBackgroundColor' => 'rgb(40, 167, 69)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgb(40, 167, 69)',
                'data' => $data,
                'fill' => true,
                'tension' => 0.3,
                'borderWidth' => 3,
                'pointRadius' => 5,
                'pointHoverRadius' => 7,
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => false,
                    'text' => 'Évolution annuelle des indemnités des collaborateurs',
                ],
            ],
        ]);

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

        $this->applyManagedUsersFilterOnReports($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $dataByMonth = array_fill(1, 12, 0);
        foreach ($rows as $row) {
            $dataByMonth[(int)$row['monthNumber']] = (float)$row['totalAmount'];
        }

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['Jan','Fév','Mars','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'],
            'datasets' => [[
                'label' => sprintf('Indemnités en %d', $year),
                'data' => array_values($dataByMonth),
                'backgroundColor' => 'rgba(13,110,253,0.65)',
                'borderColor' => 'rgb(13,110,253)',
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => false,
                    'text' => sprintf('Indemnités kilométriques par mois pour l\'année %d', $year),
                ],
            ],
        ]);

        return $chart;
    }

    private function applyManagedUsersFilterOnReportLines(QueryBuilder $qb): void
    {
        $manager = $this->getUser();

        if (!$manager instanceof User) {
            $qb->andWhere('1 = 0');
            return;
        }

        $qb
            ->join('rl.report', 'r')
            ->join('r.user', 'u')
            ->andWhere('u.managedBy = :manager')
            ->setParameter('manager', $manager);
    }

    private function applyManagedUsersFilterOnReports(QueryBuilder $qb): void
    {
        $manager = $this->getUser();

        if (!$manager instanceof User) {
            $qb->andWhere('1 = 0');
            return;
        }

        $qb
            ->join('r.user', 'u')
            ->andWhere('u.managedBy = :manager')
            ->setParameter('manager', $manager);
    }
}
