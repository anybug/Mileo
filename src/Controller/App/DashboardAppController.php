<?php

namespace App\Controller\App;

use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Order;
use App\Entity\Power;
use App\Entity\Scale;
use App\Entity\Report;
use App\Entity\Vehicule;
use App\Entity\ReportLine;
use App\Entity\UserAddress;
use App\Form\UserStep2Type;
use App\Form\BugReportType;
use App\Form\UserStep3Type;
use App\Entity\Subscription;
use Symfony\Component\Mime\Email;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\Component\Asset\Packages;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Admin\UserCrudController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Admin\VehiculeCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Component\Form\FormFactoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Form\Test\FormBuilderInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyAdminFriends\EasyAdminDashboardBundle\Service\EasyAdminDashboard;

class DashboardAppController extends AbstractDashboardController
{
    private $easyAdminDashboard;
    private $chartBuilder;
    private $entityManager;
    private $adminUrlGenerator;

    private Packages $assets;


    public function __construct(
        AdminUrlGenerator $adminUrlGenerator, 
        EasyAdminDashboard $easyAdminDashboard,
        ChartBuilderInterface $chartBuilder,
        EntityManagerInterface $entityManager, 
        FormFactoryInterface $formFactory,
        Packages $assets
    )
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->easyAdminDashboard = $easyAdminDashboard;
        $this->chartBuilder = $chartBuilder;
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
        $this->assets = $assets;
    }

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
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $step = [];

        if (!$user->getSubscription() || !$user->getSubscription()->isValid()) {

            $url = $this->adminUrlGenerator
            ->setController(UserAppCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

            return $this->redirect($url);
        }

        if(!$user->hasCompletedSetup())
        {
            if(!$user->hasCompletedStep2() || $step2){
                $form = $this->createForm(UserStep2Type::class, $user);
                $step = ['title' => "Informations personnelles et juridiques", 'number' => 2];
                $step2 = true;
            }else{
                $form = $this->formFactory->createNamed('Vehicule', UserStep3Type::class);
                $step = ['title' => "Véhicule par défaut", 'number' => 3];
            }
            
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();

                if($data instanceof Vehicule)
                {
                    $data->setUser($user);
                }

                $this->entityManager->persist($data);      
                $this->entityManager->flush();

                if(!$step2){
                    $this->addFlash(
                        'success',
                        '<span class="fs-4">Félicitations! Vous pouvez désormais profiter de Mileo, amusez-vous bien <i class="far fa-smile-wink"></i> </span>'
                    );
                }
                
                return $this->redirectToRoute('app', ['menuIndex' => 0, 'submenuIndex' => '-1']);
            }

            return $this->render('App/Dashboard/wizard.html.twig', [
                'dashboard' => $this->easyAdminDashboard->getDashboard(),
                'form' => $form->createView(),
                'step' => $step,
            ]);
        }
   
        $yearSelected = date('Y');

        $lastReport = $this->entityManager->getRepository(Report::class)->getLastReportForUser();
        if($lastReport){
            $yearSelected = $lastReport->getStartDate()->format('Y');
        }
        
        if($request->query->get('yearSelected')){
            $yearDate = \DateTimeImmutable::createFromFormat('Y', $request->query->get('yearSelected'));
            if($yearDate){
                $yearSelected = $yearDate->format('Y');
            }
        }

        $reports = $this->entityManager->getRepository(Report::class)->findByYear($yearSelected);
        $labels = [];
        $data = [];
        foreach ($reports as $report) {
            array_push($labels,$report->getStartDate()->format('m/Y'));
            array_push($data,$report->getTotal());
        }
        
        $chartAnnuel = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        
        $chartAnnuel->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Montant des indemnité en Euro par mois',
                    'backgroundColor' => '#0d6efd',
                    'borderColor' => '#0d6efd',
                    'data' => $data,
                ],
            ],
        ]);
        
        /*$chartAnnuel->setOptions([
            'scales' => [
                'y' => [
                    'suggestedMin' => 0,
                    'suggestedMax' => 100,
                ],
            ],
        ]);*/

        $reports = $this->entityManager->getRepository(Report::class)->getReportsForUser();

        $valueListYears = [];
        foreach ($reports as $report) {
            $valueListYears[] = $report->getStartDate()->format('Y');
            $report_labels[$report->getStartDate()->format('Y')] = $report->getStartDate()->format('Y');
            if(isset($report_data[$report->getStartDate()->format('Y')])){
                $report_data[$report->getStartDate()->format('Y')] += $report->getTotal();
            }else{
                $report_data[$report->getStartDate()->format('Y')] = $report->getTotal();
            }
        }
        $resultListYears = array_unique($valueListYears);

        $labels = [];
        $data = [];
        if(isset($report_labels)){
            foreach($report_labels as $val){
                array_push($labels,$val);
            }
        }
        if(isset($report_data)){
            foreach($report_data as $val){
                array_push($data,$val);
            }
        }

        $chartTotal = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        
        $chartTotal->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Indemnité kilométrique par année',
                    'backgroundColor' => '#0d6efd',
                    'borderColor' => '#0d6efd',
                    'data' => $data,
                ],
            ],
        ]);

        //check if vehicule is set up with latest Scale
        $flash  = false;
        foreach($this->getUser()->getVehicules() as $vehicule)
        {
            $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(VehiculeAppCrudController::class)
            ->setAction(Action::INDEX)
            ->set('menuIndex', 6)
            ->generateUrl()
            ;

            if(!$vehicule->hasLatestScale()){
                $flash  = true;
            }
        }

        if($flash){
            $this->addFlash(
                'info',
                '<span class="fs-4">Certains de vos véhicules ne sont pas configurés avec le dernier barème en date. <a href="'.$url.'" class=""><i class="action-icon fa fa-pen"></i> Mettre à jour mes véhicules</a></span>'
            );
        }

        return $this->render('App/Dashboard/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->getDashboard(),
            'chartAnnuel' => $chartAnnuel,
            'chartTotal' => $chartTotal,
            'years' => $resultListYears,
            'yearSelected' => $yearSelected
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
            ;
    }

    public function configureMenuItems(): iterable
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user && $user->getSubscription() && $user->getSubscription()->getPlan()) {
            $planName = strtolower(trim((string) $user->getSubscription()->getPlan()->getName()));
        }else{
            $planName = false;
        }

        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Travels');
        yield MenuItem::linkToCrud('My travels', 'fa fa-road', ReportLine::class)->setController(ReportLineAppCrudController::class);
        yield MenuItem::linkToCrud('Monthly reports', 'fa fa-road', Report::class)->setController(ReportAppCrudController::class);

        yield MenuItem::section('Parameters');
        yield MenuItem::linkToCrud('Profile', 'fa fa-id-card', User::class)->setController(UserAppCrudController::class);
        yield MenuItem::linkToCrud('My vehicules', 'fa fa-car', Vehicule::class)->setController(VehiculeAppCrudController::class);
        yield MenuItem::linkToCrud('My addresses', 'fa fa-map-marker-alt', UserAddress::class)->setController(AddressesAppCrudController::class);

        if ($planName && $planName !== 'free') {
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
}