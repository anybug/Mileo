<?php

namespace App\Controller\Admin;

use App\Controller\Admin\ActiveSubscriptionCrudController;
use App\Controller\Admin\InactiveSubscriptionCrudController;
use App\Controller\Admin\UserManagerCrudController;
use App\Controller\Admin\UserProCrudController;
use App\Entity\User;
use EasyAdminFriends\EasyAdminDashboardBundle\Service\EasyAdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_ADMIN")]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
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

    public function index(): Response
    {
        return $this->render('@EasyAdminDashboard/Default/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
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
        yield MenuItem::linkTo(OrderCrudController::class, 'Orders', 'fas fa-layer-group');
        yield MenuItem::linkTo(PlanCrudController::class,'Plans', 'fas fa-book');

        yield MenuItem::section('Subscriptions');

        yield MenuItem::linkTo(InactiveSubscriptionCrudController::class, 'Inactive subscriptions', 'fas fa-file-invoice-dollar');
        yield MenuItem::linkTo(ActiveSubscriptionCrudController::class, 'Active subscriptions', 'fas fa-file-invoice-dollar');

        yield MenuItem::section('Users');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-user');
        yield MenuItem::linkTo(UserProCrudController::class, 'Pro users', 'fa-solid fa-star');
        yield MenuItem::linkTo(UserManagerCrudController::class, 'Manager users', 'fa-solid fa-user-tie');
       
    }
}