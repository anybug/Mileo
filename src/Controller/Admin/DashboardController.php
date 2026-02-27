<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\Vehicule;
use EasyAdminFriends\EasyAdminDashboardBundle\Service\EasyAdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Asset\Packages;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
class DashboardController extends AbstractDashboardController
{
    private $easyAdminDashboard;
    private $assets;

    public function __construct(EasyAdminDashboard $easyAdminDashboard, Packages $assets)
    {
        $this->easyAdminDashboard = $easyAdminDashboard;
        $this->assets = $assets;
    }

    /** Cette interface "Super Admin" est en version Beta, à utiliser uniquement à des fins logistiques (ex: factures) */

    #[Route(path: '/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('@EasyAdminDashboard/Default/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Mileo')
            ->setTitle(sprintf('<img src="%s" />', $this->assets->getUrl('img/logo.png')))
            ;
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
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class)->setController(UserCrudController::class);
        yield MenuItem::linkToCrud('Orders', 'fas fa-layer-group', Order::class)->setController(OrderCrudController::class);
        yield MenuItem::linkToCrud('Subscriptions', 'fas fa-file-invoice-dollar', Subscription::class)->setController(SubscriptionCrudController::class);
        yield MenuItem::linkToCrud('Plans', 'fas fa-book', Plan::class)->setController(PlanCrudController::class);
        
    }
}