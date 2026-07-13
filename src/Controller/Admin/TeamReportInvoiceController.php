<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Service\Billing\TeamReportInvoiceBuilder;
use App\Service\Billing\TeamReportInvoiceStatusWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TeamReportInvoiceController extends AbstractController
{
    #[Route(
        path: '/admin/team/{manager}/reports-invoice/{year}/{month}/draft',
        name: 'admin_team_report_invoice_draft',
        methods: ['POST'],
    )]
    public function draft(
        User $manager,
        int $year,
        int $month,
        Request $request,
        TeamReportInvoiceBuilder $invoiceBuilder,
    ): Response {
        $tokenId = $this->getTokenId('Draft', $manager, $year, $month);

        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invoice = $invoiceBuilder->createOrUpdateDraft($manager, $year, $month);

        $this->addFlash(
            'success',
            sprintf('La facture brouillon #%s a été créée.', $invoice->getNum()),
        );

        return $this->redirect(
            $request->request->get('_redirect')
                ?: $request->headers->get('referer')
                ?: '/admin'
        );
    }

    #[Route(
        path: '/admin/team/{manager}/reports-invoice/{year}/{month}/{transition}',
        name: 'admin_team_report_invoice_transition',
        requirements: [
            'transition' => 'Sent|Paid|Cancelled',
        ],
        methods: ['POST'],
    )]
    public function transition(
        User $manager,
        int $year,
        int $month,
        string $transition,
        Request $request,
        TeamReportInvoiceBuilder $invoiceBuilder,
        TeamReportInvoiceStatusWorkflow $invoiceWorkflow,
    ): Response {
        $tokenId = $this->getTokenId($transition, $manager, $year, $month);

        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invoice = $invoiceBuilder->createOrUpdateDraft($manager, $year, $month);

        if ($invoice->getStatusAsString() === $transition) {
            $this->addFlash('info', 'Cette facture a déjà ce statut.');

            return $this->redirect(
                $request->request->get('_redirect')
                    ?: $request->headers->get('referer')
                    ?: '/admin'
            );
        }

        match ($transition) {
            InvoiceStatus::SENT->value => $invoiceWorkflow->sent($invoice),
            InvoiceStatus::PAID->value => $invoiceWorkflow->paid($invoice),
            InvoiceStatus::CANCELLED->value => $invoiceWorkflow->cancelled($invoice),
            default => throw $this->createNotFoundException(),
        };

        $this->addFlash('success', 'Le statut de la facture a été mis à jour.');

        return $this->redirect(
            $request->request->get('_redirect')
                ?: $request->headers->get('referer')
                ?: '/admin'
        );
    }

    private function getTokenId(string $action, User $manager, int $year, int $month): string
    {
        return sprintf(
            'team_report_invoice_%s_%d_%04d_%02d',
            $action,
            $manager->getId(),
            $year,
            $month,
        );
    }
}