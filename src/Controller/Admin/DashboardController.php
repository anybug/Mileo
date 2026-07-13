<?php

namespace App\Controller\Admin;

use App\Controller\Admin\ActiveSubscriptionCrudController;
use App\Controller\Admin\InactiveSubscriptionCrudController;
use App\Controller\Admin\OrderCrudController;
use App\Controller\Admin\PlanCrudController;
use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\UserProCrudController;
use App\Controller\Admin\UserTeamCrudController;
use App\Controller\Admin\InvoiceCrudController;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyAdminFriends\EasyAdminDashboardBundle\Service\EasyAdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Model\Chart;

#[IsGranted("ROLE_ADMIN")]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private $easyAdminDashboard;
    private $assets;

    public function __construct(
        EasyAdminDashboard $easyAdminDashboard,
        Packages $assets,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->easyAdminDashboard = $easyAdminDashboard;
        $this->assets = $assets;
    }

    /** Cette interface "Super Admin" est en version Beta, à utiliser uniquement à des fins logistiques (ex: factures) */

    public function index(): Response
    {

        $metrics = $this->getSubscriptionMetrics();

        return $this->render('Admin/Dashboard/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
            'subscriptionMetrics' => $metrics,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle(sprintf('<img src="%s" />', $this->assets->getUrl('img/logo.png')))
            ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('app');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->showEntityActionsInlined()
            ->overrideTemplate('layout', 'Team/advanced_layout.html.twig')
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

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');
        
        yield MenuItem::section('Management');
        yield MenuItem::linkTo(OrderCrudController::class, 'Orders', 'fas fa-layer-group');
        yield MenuItem::linkTo(PlanCrudController::class,'Plans', 'fas fa-book');

        yield MenuItem::section('Subscriptions');

        yield MenuItem::linkTo(InactiveSubscriptionCrudController::class, 'Inactive subscriptions', 'fas fa-file-invoice-dollar');
        yield MenuItem::linkTo(ActiveSubscriptionCrudController::class, 'Active subscriptions', 'fas fa-file-invoice-dollar');

        yield MenuItem::section('Users');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-user');
        yield MenuItem::linkTo(UserProCrudController::class, 'Pro users', 'fa-solid fa-star');
        yield MenuItem::linkTo(UserTeamCrudController::class, 'Team users', 'fa-solid fa-user-tie');

        yield MenuItem::section('Invoices');
        yield MenuItem::linkTo(InvoiceCrudController::class, 'Team invoices', 'fa-solid fa-file-invoice-dollar');
       
    }

    private function getSubscriptionMetrics(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $in8Days = $today->modify('+8 days');
        $in31Days = $today->modify('+31 days');

        $result = $this->entityManager->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN s.subscription_end < :today THEN 1 ELSE 0 END) AS expired',
                'SUM(CASE WHEN s.subscription_end >= :today AND s.subscription_end < :tomorrow THEN 1 ELSE 0 END) AS expiresToday',
                'SUM(CASE WHEN s.subscription_end >= :tomorrow AND s.subscription_end < :in8Days THEN 1 ELSE 0 END) AS expiresIn7Days',
                'SUM(CASE WHEN s.subscription_end >= :in8Days AND s.subscription_end < :in31Days THEN 1 ELSE 0 END) AS expiresIn30Days',
                'SUM(CASE WHEN s.subscription_end >= :in31Days THEN 1 ELSE 0 END) AS activeMoreThan30Days'
            )
            ->from(Subscription::class, 's')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('in8Days', $in8Days)
            ->setParameter('in31Days', $in31Days)
            ->getQuery()
            ->getSingleResult();

        $metrics = [
            'expired' => (int) ($result['expired'] ?? 0),
            'expiresToday' => (int) ($result['expiresToday'] ?? 0),
            'expiresIn7Days' => (int) ($result['expiresIn7Days'] ?? 0),
            'expiresIn30Days' => (int) ($result['expiresIn30Days'] ?? 0),
            'activeMoreThan30Days' => (int) ($result['activeMoreThan30Days'] ?? 0),
        ];

        $metrics['active'] =
            $metrics['activeMoreThan30Days']
            + $metrics['expiresIn30Days']
            + $metrics['expiresIn7Days']
            + $metrics['expiresToday'];

        $metrics['expiringWithin30Days'] =
            $metrics['expiresIn30Days']
            + $metrics['expiresIn7Days']
            + $metrics['expiresToday'];

        $metrics['expiringWithin7Days'] =
            $metrics['expiresIn7Days']
            + $metrics['expiresToday'];

        $metrics['expiredUrl'] = $this->inactiveSubscriptionUrl();

        $metrics['activeUrl'] = $this->activeSubscriptionUrl();

        $metrics['expiresTodayUrl'] = $this->activeSubscriptionUrl(0);

        $metrics['expiringWithin7DaysUrl'] = $this->activeSubscriptionUrl(7);

        $metrics['expiringWithin30DaysUrl'] = $this->activeSubscriptionUrl(30);


        return $metrics;
    }

    private function activeSubscriptionUrl(?int $expiryDays = null): string
    {
        $url = (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setDashboard(self::class)
            ->setController(ActiveSubscriptionCrudController::class)
            ->setAction(Crud::PAGE_INDEX);

        if (null !== $expiryDays) {
            $url->set(
                'filters[subscription_end]',
                (string) $expiryDays
            );
        }

        return $url->generateUrl();
    }

    private function inactiveSubscriptionUrl(): string
    {
        return (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setDashboard(self::class)
            ->setController(InactiveSubscriptionCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();
    }

}