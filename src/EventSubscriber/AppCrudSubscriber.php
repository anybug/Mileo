<?php

namespace App\EventSubscriber;

use App\Controller\App\ReportAppCrudController;
use App\Controller\App\ReportLineAppCrudController;
use App\Controller\App\ReportLineSidebarCrudController;
use App\Entity\Report;
use App\Entity\ReportLine;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AppCrudSubscriber implements EventSubscriberInterface
{
    private $adminUrlGenerator;
    private $entityManager;
    private $security;

    public function __construct(
        AdminUrlGenerator $adminUrlGenerator,
        EntityManagerInterface $entityManager,
        Security $security
    ) {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public static function getSubscribedEvents()
    {
        return [
            BeforeCrudActionEvent::class => ['redirectToIndex']
        ];
    }

    public function redirectToIndex(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();

        $controller = $context->getCrud()?->getControllerFqcn();
        $action = $context->getCrud()?->getCurrentAction();
        $entityFqcn = $context->getEntity()?->getFqcn();

        if (!$entityFqcn) {
            return;
        }

        if ($controller === ReportLineSidebarCrudController::class) {
            return;
        }

        if ($action !== Action::INDEX) {
            return;
        }

        $entity = new $entityFqcn();

        if ($entity instanceof ReportLine) {
            if (!$context->getRequest()->isXmlHttpRequest()) {
                if (
                    !$context->getRequest()->query->has('filters')
                    || (
                        $context->getRequest()->query->has('filters')
                        && !$this->guessLinesFromPeriod($context->getRequest()->query->all()['filters'])
                    )
                ) {
                    $filterParams = null;

                    $reportline = $this->entityManager
                        ->getRepository(ReportLine::class)
                        ->getLastLineForUser();

                    if ($reportline) {
                        $filterParams = $reportline->getTravelDate()->format('F/Y');
                    }

                    if ($filterParams) {
                        $url = $this->adminUrlGenerator
                            ->setController(ReportLineAppCrudController::class)
                            ->setAction(Action::INDEX)
                            ->set('filters[period][value]', $filterParams)
                            ->generateUrl();

                        $event->setResponse(new RedirectResponse($url));
                    }
                }
            }
        }

        if ($entity instanceof Report) {
            if (
                !$context->getRequest()->query->has('filters')
                || (
                    $context->getRequest()->query->has('filters')
                    && !$this->guessReportsFromPeriod($context->getRequest()->query->all()['filters'])
                )
            ) {
                $filterParams = null;

                $report = $this->entityManager
                    ->getRepository(Report::class)
                    ->getLastReportForUser();

                if ($report) {
                    $period = $this->security->getUser()->generateBalancePeriodByReport($report);
                    $filterParams = $this->security->getUser()->getFormattedBalancePeriod($period);
                }

                if ($filterParams) {
                    $url = $this->adminUrlGenerator
                        ->setController(ReportAppCrudController::class)
                        ->setAction(Action::INDEX)
                        ->set('filters[Period][value]', $filterParams)
                        ->generateUrl();

                    $event->setResponse(new RedirectResponse($url));
                }
            }
        }
    }

    private function guessLinesFromPeriod($filters): bool
    {
        if (!isset($filters['period']['value']) || empty($filters['period']['value'])) {
            return false;
        }

        $period = $filters['period']['value'];

        if (!str_contains($period, '/')) {
            return false;
        }

        [$month, $year] = explode('/', $period);

        $report = $this->entityManager
            ->getRepository(Report::class)
            ->findByYearAndMonth($year, $month);

        return $report !== null;
    }

    private function guessReportsFromPeriod($filters): bool
    {
        if (!isset($filters['Period']['value']) || empty($filters['Period']['value'])) {
            return false;
        }

        $period = $filters['Period']['value'];

        if (!str_contains($period, ' -> ')) {
            return false;
        }

        [$start, $end] = explode(' -> ', $period);

        $reports = $this->entityManager
            ->getRepository(Report::class)
            ->findByPeriod($start, $end);

        return count($reports) > 0;
    }
}