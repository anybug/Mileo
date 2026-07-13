<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use App\Service\Billing\TeamReportInvoicePricing;
use App\Utils\TeamReportInvoicePdf;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\NotBlank;

#[IsGranted('ROLE_ADMIN')]
final class InvoiceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly RequestStack $requestStack,
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Invoice::class;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder(
            $searchDto,
            $entityDto,
            $fields,
            $filters,
        );

        $queryBuilder
            ->andWhere('entity.type = :teamReportType')
            ->setParameter('teamReportType', Invoice::TYPE_TEAM_REPORT);

        return $queryBuilder;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Facture Team')
            ->setEntityLabelInPlural('Factures Team')
            ->setPageTitle(Crud::PAGE_INDEX, 'Factures Team')
            ->setPageTitle(Crud::PAGE_DETAIL, fn (Invoice $invoice) => 'Facture #'.$invoice->getNum())
            ->setPageTitle(Crud::PAGE_EDIT, fn (Invoice $invoice) => 'Modifier la facture #'.$invoice->getNum())
            ->setDefaultSort([
                'id' => 'DESC',
            ])
            ->setSearchFields([
                'num',
                'type',
                'status',
                'teamManager.email',
                'teamManager.company',
                'teamManager.firstName',
                'teamManager.lastName',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $downloadFacturX = Action::new('downloadFacturX', 'Télécharger Factur-X', 'fa-solid fa-file-invoice')
            ->linkToCrudAction('downloadFacturX')
            ->displayIf(static fn (Invoice $invoice): bool => $invoice->getFacturXPath() !== null && is_file($invoice->getFacturXPath()));

        $previewPdf = Action::new('previewPdf', 'Prévisualiser', 'fa-solid fa-eye')
            ->linkToCrudAction('previewPdf')
            ->addCssClass('btn btn-outline-primary');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $previewPdf)
            ->add(Crud::PAGE_INDEX, $downloadFacturX)
            ->add(Crud::PAGE_DETAIL, $previewPdf)
            ->add(Crud::PAGE_DETAIL, $downloadFacturX)

            ->update(Crud::PAGE_INDEX, Action::EDIT, static function (Action $action): Action {
                return $action
                    ->setLabel('Modifier')
                    ->displayIf(static fn (Invoice $invoice): bool => in_array(
                        $invoice->getStatus(),
                        [InvoiceStatus::DRAFT, InvoiceStatus::CANCELLED],
                        true,
                    ));
            })
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static function (Action $action): Action {
                return $action
                    ->setLabel('Modifier')
                    ->displayIf(static fn (Invoice $invoice): bool => in_array(
                        $invoice->getStatus(),
                        [InvoiceStatus::DRAFT, InvoiceStatus::CANCELLED],
                        true,
                    ));
            })

            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'Brouillon' => InvoiceStatus::DRAFT,
                'Envoyée' => InvoiceStatus::SENT,
                'Payée' => InvoiceStatus::PAID,
                'Annulée' => InvoiceStatus::CANCELLED,
            ]))
            ->add(NumericFilter::new('billingYear', 'Année de facturation'))
            ->add(NumericFilter::new('billingMonth', 'Mois de facturation'))
            ->add(DateTimeFilter::new('sentAt', 'Envoyée le'))
            ->add(DateTimeFilter::new('paidAt', 'Payée le'));
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield IdField::new('id')
                ->hideOnForm();

            yield IntegerField::new('num', 'N° facture');

            yield TextField::new('statusLabel', 'Statut')
                ->formatValue(static function ($value, Invoice $invoice): string {
                    return sprintf(
                        '<span class="badge %s">%s</span>',
                        $invoice->getStatusBadgeClass(),
                        $invoice->getStatusLabel(),
                    );
                })
                ->renderAsHtml();

            yield Field::new('teamManager', 'Manager')
                ->formatValue(static function ($value, Invoice $invoice): string {
                    $manager = $invoice->getTeamManager();

                    if ($manager === null) {
                        return '-';
                    }

                    $name = $manager->getCompany()
                        ?: trim(($manager->getFirstName() ?? '').' '.($manager->getLastName() ?? ''));

                    return $name !== '' ? $name : $manager->getEmail();
                });

            yield TextField::new('billingPeriod', 'Période');

            yield Field::new('reports', 'Rapports')
                ->formatValue(static fn ($value, Invoice $invoice): int => $invoice->getReports()->count());

            yield NumberField::new('totalHt', 'Total HT')
                ->formatValue(static fn ($value): string => $value === null ? '-' : number_format((float) $value, 2, ',', ' ').' €');

            yield NumberField::new('vatAmount', 'TVA')
                ->formatValue(static fn ($value): string => $value === null ? '-' : number_format((float) $value, 2, ',', ' ').' €');

            yield NumberField::new('totalTtc', 'Total TTC')
                ->formatValue(static fn ($value): string => $value === null ? '-' : number_format((float) $value, 2, ',', ' ').' €');

            yield DateTimeField::new('sentAt', 'Envoyée le')
                ->setFormat('dd/MM/yyyy HH:mm');

            yield DateTimeField::new('paidAt', 'Payée le')
                ->setFormat('dd/MM/yyyy HH:mm');

            return;
        }

        if ($pageName === Crud::PAGE_DETAIL) {

            yield FormField::addTab('Facture')
                ->setIcon('fa-solid fa-file-invoice');

            yield IdField::new('id');

            yield IntegerField::new('num', 'N° facture');

            yield TextField::new('statusLabel', 'Statut')
                ->formatValue(static function ($value, Invoice $invoice): string {
                    return sprintf(
                        '<span class="badge %s">%s</span>',
                        $invoice->getStatusBadgeClass(),
                        $invoice->getStatusLabel(),
                    );
                })
                ->renderAsHtml();

            yield Field::new('teamManager', 'Manager')
                ->formatValue(static function ($value, Invoice $invoice): string {
                    $manager = $invoice->getTeamManager();

                    if ($manager === null) {
                        return '-';
                    }

                    $name = $manager->getCompany()
                        ?: trim(($manager->getFirstName() ?? '').' '.($manager->getLastName() ?? ''));

                    return $name !== '' ? $name : $manager->getEmail();
                });

            yield Field::new('order', 'Commande')
                ->formatValue(static function ($value, Invoice $invoice): string {
                    $order = $invoice->getOrder();

                    return $order !== null && $order->getId() !== null
                        ? '#'.$order->getId()
                        : '-';
                });

            yield TextField::new('billingPeriod', 'Période');

            yield Field::new('reports', 'Rapports')
                ->formatValue(static fn ($value, Invoice $invoice): int => $invoice->getReports()->count());

            yield TextareaField::new('modificationReason', 'Motif de modification')
                ->formatValue(static fn ($value): string => $value ?: '-');

            yield NumberField::new('totalHt', 'Total HT')
                ->formatValue(static fn ($value): string => $value === null ? '-' : number_format((float) $value, 2, ',', ' ').' €');

            yield NumberField::new('vatAmount', 'TVA')
                ->formatValue(static fn ($value): string => $value === null ? '-' : number_format((float) $value, 2, ',', ' ').' €');

            yield NumberField::new('totalTtc', 'Total TTC')
                ->formatValue(static fn ($value): string => $value === null ? '-' : number_format((float) $value, 2, ',', ' ').' €');

            yield DateTimeField::new('sentAt', 'Envoyée le')
                ->setFormat('dd/MM/yyyy HH:mm');

            yield DateTimeField::new('paidAt', 'Payée le')
                ->setFormat('dd/MM/yyyy HH:mm');

            yield TextField::new('facturXPath', 'Factur-X')
                ->formatValue(function ($value, Invoice $invoice): string {
                    if ($invoice->getFacturXPath() === null || !is_file($invoice->getFacturXPath())) {
                        return '-';
                    }

                    $url = $this->adminUrlGenerator
                        ->unsetAll()
                        ->setController(self::class)
                        ->setAction('downloadFacturX')
                        ->setEntityId($invoice->getId())
                        ->generateUrl();

                    return sprintf(
                        '<a href="%s" class="btn btn-success">
                            <i class="fa-solid fa-file-invoice me-1"></i> Télécharger Factur-X
                        </a>',
                        htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                    );
                })
                ->renderAsHtml();

            yield FormField::addTab('Prévisualisation')
                ->setIcon('fa-solid fa-eye');

            yield Field::new('id', false)
                ->setTemplatePath('Admin/Invoice/team_report_invoice_preview.html.twig');

            return;
        }

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield IntegerField::new('num', 'N° facture')
                ->setFormTypeOption('disabled', true);

            yield TextField::new('statusLabel', 'Statut')
                ->setFormTypeOption('disabled', true);

            yield TextField::new('billingPeriod', 'Période')
                ->setFormTypeOption('disabled', true);

            $currentInvoice = $this->getCurrentInvoice();

            if ($currentInvoice?->getStatus() === InvoiceStatus::CANCELLED) {
                yield TextareaField::new('modificationReason', 'Motif de modification')
                    ->setHelp('Obligatoire pour repasser une facture annulée en brouillon.')
                    ->setRequired(true)
                    ->setFormTypeOption('constraints', [
                        new NotBlank([
                            'message' => 'Vous devez préciser un motif de modification pour repasser une facture annulée en brouillon.',
                        ]),
                    ]);
            }

            yield NumberField::new('totalHt', 'Total HT')
                ->setNumDecimals(2)
                ->setFormTypeOption('disabled', true);

            yield NumberField::new('vatAmount', 'TVA')
                ->setNumDecimals(2)
                ->setFormTypeOption('disabled', true);

            yield NumberField::new('totalTtc', 'Total TTC')
                ->setNumDecimals(2)
                ->setFormTypeOption('disabled', true);

            return;
        }
    }

    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance,
    ): void {
        if (!$entityInstance instanceof Invoice) {
            return;
        }

        if (!in_array($entityInstance->getStatus(), [InvoiceStatus::DRAFT, InvoiceStatus::CANCELLED], true)) {
            throw $this->createAccessDeniedException(
                'Seules les factures en brouillon ou annulées peuvent être modifiées.',
            );
        }

        $wasCancelled = $entityInstance->getStatus() === InvoiceStatus::CANCELLED;
        if ($wasCancelled) {
            $modificationReason = trim((string) $entityInstance->getModificationReason());

            $entityInstance
                ->setModificationReason($modificationReason)
                ->setStatus(InvoiceStatus::DRAFT)
                ->setSentAt(null)
                ->setPaidAt(null)
                ->setFacturXPath(null);

            $this->addFlash(
                'info',
                'La facture annulée a été repassée en brouillon avec un motif de modification.',
            );
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function downloadFacturX(
        Request $request,
        InvoiceRepository $invoiceRepository,
    ): Response {
        $invoice = $this->getInvoiceFromRequest($request, $invoiceRepository);
        $path = $invoice->getFacturXPath();

        if ($path === null || !is_file($path)) {
            throw $this->createNotFoundException('Le fichier Factur-X est introuvable.');
        }

        return $this->file(
            $path,
            basename($path),
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        );
    }
    private function getInvoiceFromRequest(
        Request $request,
        InvoiceRepository $invoiceRepository,
    ): Invoice {
        $invoiceId = $request->query->get('entityId');

        if ($invoiceId === null) {
            throw $this->createNotFoundException('Identifiant de facture manquant.');
        }

        $invoice = $invoiceRepository->find($invoiceId);

        if (!$invoice instanceof Invoice) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $invoice;
    }

    private function getCurrentInvoice(): ?Invoice
    {
        $context = $this->adminContextProvider->getContext();
        $entity = $context?->getEntity()?->getInstance();

        if ($entity instanceof Invoice) {
            return $entity;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        $entityId = $request->query->get('entityId');

        if ($entityId === null) {
            return null;
        }

        $invoice = $this->invoiceRepository->find($entityId);

        return $invoice instanceof Invoice ? $invoice : null;
    }

    public function previewPdf(
        Request $request,
        InvoiceRepository $invoiceRepository,
        TeamReportInvoicePricing $pricing,
    ): Response {
        $invoice = $this->getInvoiceFromRequest($request, $invoiceRepository);

        if (!$invoice->isTeamReportInvoice()) {
            throw $this->createNotFoundException('Cette facture n’est pas une facture Team.');
        }

        $pricingDetails = $pricing->computeForInvoice($invoice);
        $pdfContent = (new TeamReportInvoicePdf())->generatePdf($invoice, $pricingDetails);

        $filename = sprintf(
            'Mileo_Facture_%s_preview.pdf',
            $invoice->getNum() ?? $invoice->getId(),
        );

        $isInline = $request->query->getBoolean('inline', true);

        $disposition = $isInline
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        $response = new Response($pdfContent);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Cache-Control', 'max-age=0, must-revalidate, private');
        $response->headers->set('Pragma', 'public');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                $disposition,
                $filename,
            ),
        );

        return $response;
    }
}