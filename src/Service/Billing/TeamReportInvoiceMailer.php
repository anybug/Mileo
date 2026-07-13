<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Invoice;
use LogicException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class TeamReportInvoiceMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendFacturXToManager(Invoice $invoice, string $facturXPath): void
    {
        if (!$invoice->isTeamReportInvoice()) {
            throw new LogicException('Cette facture n’est pas une facture de rapports.');
        }

        $manager = $invoice->getTeamManager();

        if ($manager === null) {
            throw new LogicException('Impossible d’envoyer la facture : aucun manager lié.');
        }

        if (!$manager->getEmail()) {
            throw new LogicException('Impossible d’envoyer la facture : le manager n’a pas d’adresse e-mail.');
        }

        if (!is_file($facturXPath)) {
            throw new LogicException('Impossible d’envoyer la facture : le fichier Factur-X est introuvable.');
        }

        $managerName = $manager->getCompany()
            ?: trim(($manager->getFirstName() ?? '').' '.($manager->getLastName() ?? ''));

        $period = sprintf(
            '%02d/%04d',
            $invoice->getBillingMonth(),
            $invoice->getBillingYear(),
        );

        $filename = sprintf(
            'Mileo_Facture_%s_Factur-X.pdf',
            $invoice->getNum(),
        );

        $email = (new TemplatedEmail())
            ->to(new Address($manager->getEmail(), $managerName ?: ''))
            ->subject(sprintf('Votre facture Mileo n° %s', $invoice->getNum()))
            ->htmlTemplate('Emails/teamReportInvoiceSent.html.twig')
            ->context([
                'invoice' => $invoice,
                'manager' => $manager,
                'period' => $period,
                'totalTtc' => $invoice->getTotalTtc(),
            ])
            ->attachFromPath(
                $facturXPath,
                $filename,
                'application/pdf',
            );

        $contactEmail = trim((string) ($_ENV['CONTACT_EMAIL'] ?? ''));

        if ('' !== $contactEmail) {
            $email->from(new Address($contactEmail, 'Mileo'));
        }

        $adminEmail = trim((string) ($_ENV['ADMIN_EMAIL'] ?? ''));

        if ('' !== $adminEmail) {
            $email->bcc(new Address($adminEmail));
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            throw new LogicException(sprintf(
                'Impossible d’envoyer la facture #%s au manager %s : %s',
                $invoice->getNum(),
                $manager->getEmail(),
                $exception->getMessage(),
            ), 0, $exception);
        }
    }
}