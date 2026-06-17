<?php

namespace App\Controller\Team;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\User;
use App\Entity\UserAddress;
use App\Entity\Vehicule;
use App\Service\ChartService;
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
use Symfony\UX\Chartjs\Model\Chart;

#[IsGranted("ROLE_MANAGER")]
#[AdminDashboard(routePath: '/manager', routeName: 'manager_dashboard')]
class DashboardManagerController extends AbstractDashboardController
{
    public function __construct(
        private readonly Packages $assets,
        private readonly EasyAdminDashboard $easyAdminDashboard,
        private readonly RequestStack $requestStack,
        private readonly ChartService $chartService,
        private readonly EntityManagerInterface $entityManager,
    ) {
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

        $topCollaboratorIndemnitiesChart = $this->createTopCollaboratorIndemnitiesChart($yearSelected);

        return $this->render('Team/Dashboard/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
            'years' => $years,
            'yearSelected' => $yearSelected,
            'chartTripsByMonth' => $chartTripsByMonth,
            'chartTripsByYear' => $chartTripsByYear,
            'chartAmountByMonth' => $chartAmountByMonth,
            'chartAmountByYear' => $chartAmountByYear,
            'topCollaboratorIndemnitiesChart' => $topCollaboratorIndemnitiesChart,
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

    private function getTopCollaboratorIndemnities(int $year, int $limit = 10): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'u.id AS userId',
                'u.first_name AS firstName',
                'u.last_name AS lastName',
                'u.email AS email',
                'COALESCE(SUM(rl.km_total), 0) AS totalKm',
                'COALESCE(SUM(rl.amount), 0) AS totalAmount'
            )
            ->from(ReportLine::class, 'rl')
            ->where('rl.travel_date IS NOT NULL')
            ->andWhere('YEAR(rl.travel_date) = :year')
            ->setParameter('year', $year)
            ->groupBy('u.id')
            ->addGroupBy('u.first_name')
            ->addGroupBy('u.last_name')
            ->addGroupBy('u.email')
            ->orderBy('totalAmount', 'DESC')
            ->setMaxResults($limit);

        $this->applyManagedUsersFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        return array_map(static function (array $row): array {
            $firstName = trim((string) ($row['firstName'] ?? ''));
            $lastName = trim((string) ($row['lastName'] ?? ''));
            $fullName = trim($firstName.' '.$lastName);

            if ($fullName === '') {
                $fullName = (string) ($row['email'] ?? 'Collaborateur');
            }

            return [
                'userId' => (int) $row['userId'],
                'fullName' => $fullName,
                'email' => (string) ($row['email'] ?? ''),
                'totalKm' => (int) $row['totalKm'],
                'totalAmount' => (float) $row['totalAmount'],
            ];
        }, $rows);
    }

    private function truncateLabel(string $string, int $length = 20, string $suffix = '...'): string
    {
        if (mb_strlen($string, 'UTF-8') <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length, 'UTF-8') . $suffix;
    }

    private function createTopCollaboratorIndemnitiesChart(int $year): ?Chart
    {

        $topCollaboratorIndemnities = $this->getTopCollaboratorIndemnities($year, 10);

        $labels = array_map(
            fn ($value) => $this->truncateLabel((string) $value, 30),
            array_column($topCollaboratorIndemnities, 'fullName')
        );

        $data = array_column($topCollaboratorIndemnities, 'totalAmount');
        $kms = array_column($topCollaboratorIndemnities, 'totalKm');

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_PIE,
            $labels,
            $data,
            $label = sprintf('Top 10 des IK des collaborateurs en %d', $year)
        );

        /*$chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Montant des IK (€)',
                    'data' => $amounts,
                    'backgroundColor' => '#5368d5',
                    'borderColor' => '#5368d5',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Distance des IK (km)',
                    'data' => $kms,
                    'backgroundColor' => '#f28e2b',
                    'borderColor' => '#f28e2b',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'yAxisID' => 'y1',
                ],
            ],
        ]);*/

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

        $this->applyManagedUsersFilterOnReportLines($qb);

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

        $this->applyManagedUsersFilterOnReportLines($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $dataByMonth = array_fill(1, 12, 0);
        foreach ($rows as $row) {
            $dataByMonth[(int) $row['monthNumber']] = (int) $row['total'];
        }

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_BAR,
            $labels = ['Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            count(array_filter($dataByMonth)) > 0 ? array_values($dataByMonth) : [],
            $label = sprintf('Nombre de trajets des collaborateurs en %d', $year)
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

        $this->applyManagedUsersFilterOnReports($qb);

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
            $label = 'Indemnité kilométrique des collaborateurs par année'
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

        $this->applyManagedUsersFilterOnReports($qb);

        $rows = $qb->getQuery()->getScalarResult();

        $dataByMonth = array_fill(1, 12, 0);
        foreach ($rows as $row) {
            $dataByMonth[(int)$row['monthNumber']] = (float)$row['totalAmount'];
        }

        $chart = $this->chartService->createDataChart(
            Chart::TYPE_LINE,
            $labels = ['Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            count(array_filter($dataByMonth)) > 0 ? array_values($dataByMonth) : [],
            $label = sprintf('Montants des IK des collaborateurs en %d', $year)
        );

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
