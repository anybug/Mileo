<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Report;
use App\Entity\User;
use App\Enum\ReportStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

final class ReportWorkflow
{
    public function __construct(
        #[Target('report_status')]
        private readonly WorkflowInterface $reportStatusStateMachine,

        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,

        #[Autowire('%env(CONTACT_EMAIL)%')]
        private readonly string $mailerAdmin,
    ) {
    }

    public function getStatus(Report $report): ReportStatus
    {
        return $report->getStatus() ?? ReportStatus::DRAFT;
    }

    public function isDraft(Report $report): bool
    {
        return $this->getStatus($report) === ReportStatus::DRAFT;
    }

    public function isSent(Report $report): bool
    {
        return $this->getStatus($report) === ReportStatus::SENT;
    }

    public function isValidated(Report $report): bool
    {
        return $this->getStatus($report) === ReportStatus::VALIDATED;
    }

    public function isInvalidated(Report $report): bool
    {
        return $this->getStatus($report) === ReportStatus::INVALIDATED;
    }

    public function isEditable(Report $report): bool
    {
        return in_array(
            $this->getStatus($report),
            [
                ReportStatus::DRAFT,
                ReportStatus::INVALIDATED,
            ],
            true,
        );
    }

    public function getStatusLabel(Report $report): string
    {
        return $this->getStatus($report)->label();
    }

    public function canMarkAsSent(Report $report): bool
    {
        return $this->reportStatusStateMachine->can(
            $report,
            ReportStatus::SENT->value,
        );
    }

    public function canMarkAsValidated(Report $report): bool
    {
        return $this->reportStatusStateMachine->can(
            $report,
            ReportStatus::VALIDATED->value,
        );
    }

    public function canMarkAsInvalidated(Report $report): bool
    {
        return $this->reportStatusStateMachine->can(
            $report,
            ReportStatus::INVALIDATED->value,
        );
    }

    /**
     * Envoi du rapport par le membre à son manager.
     */
    public function markAsSent(
        Report $report,
        User $member,
    ): void {
        $this->assertReportBelongsToMember(
            $report,
            $member,
        );

        $manager = $member->getManagedBy();

        if (!$manager instanceof User) {
            throw new LogicException(
                'Aucun manager n’est associé à ce membre.',
            );
        }

        $managerEmail = trim((string) $manager->getEmail());

        if ($managerEmail === '') {
            throw new LogicException(
                'Le manager ne possède pas d’adresse email.',
            );
        }

        $this->applyTransition(
            $report,
            ReportStatus::SENT,
        );

        /*
         * Suppression d’une éventuelle ancienne date
         * après une invalidation puis un nouvel envoi.
         */
        $report->setValidateDate(null);

        $this->save($report);

        $this->sendNotification(
            recipient: $managerEmail,
            subject: sprintf(
                'Rapport IK à valider — %s',
                $report->getPeriod(),
            ),
            title: 'Un rapport IK attend votre validation',
            message: sprintf(
                '%s vous a envoyé son rapport IK pour la période %s.',
                $this->getUserDisplayName($member),
                $report->getPeriod(),
            ),
            report: $report,
            action: 'sent',
            reason: null,
        );
    }

    /**
     * Validation du rapport par le manager.
     */
    public function markAsValidated(
        Report $report,
        User $manager,
    ): void {
        $this->assertManagerCanReview(
            $report,
            $manager,
        );

        $this->applyTransition(
            $report,
            ReportStatus::VALIDATED,
        );

        $report->setValidateDate(
            new \DateTimeImmutable(),
        );

        $this->save($report);

        $member = $report->getUser();

        if (!$member instanceof User) {
            return;
        }

        $memberEmail = trim((string) $member->getEmail());

        if ($memberEmail === '') {
            return;
        }

        $this->sendNotification(
            recipient: $memberEmail,
            subject: sprintf(
                'Rapport IK validé — %s',
                $report->getPeriod(),
            ),
            title: 'Votre rapport IK a été validé',
            message: sprintf(
                'Votre manager a validé votre rapport IK pour la période %s.',
                $report->getPeriod(),
            ),
            report: $report,
            action: 'validated',
            reason: null,
        );
    }

    /**
     * Invalidation du rapport par le manager.
     */
    public function markAsInvalidated(
        Report $report,
        User $manager,
        ?string $reason = null,
    ): void {
        $this->assertManagerCanReview(
            $report,
            $manager,
        );

        $reason = $reason !== null
            ? trim($reason)
            : null;

        if ($reason === '') {
            $reason = null;
        }

        $this->applyTransition(
            $report,
            ReportStatus::INVALIDATED,
        );

        $report->setValidateDate(null);

        $this->save($report);

        $member = $report->getUser();

        if (!$member instanceof User) {
            return;
        }

        $memberEmail = trim((string) $member->getEmail());

        if ($memberEmail === '') {
            return;
        }

        $this->sendNotification(
            recipient: $memberEmail,
            subject: sprintf(
                'Rapport IK à corriger — %s',
                $report->getPeriod(),
            ),
            title: 'Votre rapport IK a été invalidé',
            message: sprintf(
                'Votre manager a invalidé votre rapport IK pour la période %s. Vous pouvez le corriger puis le renvoyer.',
                $report->getPeriod(),
            ),
            report: $report,
            action: 'invalidated',
            reason: $reason,
        );
    }

    private function applyTransition(
        Report $report,
        ReportStatus $targetStatus,
    ): void {
        $transition = $targetStatus->value;

        if (
            !$this->reportStatusStateMachine->can(
                $report,
                $transition,
            )
        ) {
            throw new LogicException(sprintf(
                'Impossible de passer le rapport du statut "%s" au statut "%s".',
                $this->getStatusLabel($report),
                $targetStatus->label(),
            ));
        }

        $this->reportStatusStateMachine->apply(
            $report,
            $transition,
        );
    }

    private function save(Report $report): void
    {
        $this->entityManager->persist($report);
        $this->entityManager->flush();
    }

    private function assertReportBelongsToMember(
        Report $report,
        User $member,
    ): void {
        $reportMember = $report->getUser();

        if (!$reportMember instanceof User) {
            throw new LogicException(
                'Le rapport ne possède aucun membre.',
            );
        }

        if ($reportMember->getId() !== $member->getId()) {
            throw new LogicException(
                'Vous ne pouvez pas envoyer le rapport d’un autre membre.',
            );
        }
    }

    private function assertManagerCanReview(
        Report $report,
        User $manager,
    ): void {
        $member = $report->getUser();

        if (!$member instanceof User) {
            throw new LogicException(
                'Le rapport ne possède aucun membre.',
            );
        }

        $expectedManager = $member->getManagedBy();

        if (!$expectedManager instanceof User) {
            throw new LogicException(
                'Aucun manager n’est associé au membre de ce rapport.',
            );
        }

        if ($expectedManager->getId() !== $manager->getId()) {
            throw new LogicException(
                'Vous n’êtes pas le manager de ce membre.',
            );
        }
    }

    private function sendNotification(
        string $recipient,
        string $subject,
        string $title,
        string $message,
        Report $report,
        string $action,
        ?string $reason,
    ): void {
        $url = $this->router->generate(
            'app',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(
                new Address(
                    $this->mailerAdmin,
                    'Mileo',
                ),
            )
            ->to(new Address($recipient))
            ->subject($subject)
            ->htmlTemplate(
                'emails/report_status.html.twig',
            )
            ->context([
                'title' => $title,
                'message' => $message,
                'report' => $report,
                'action' => $action,
                'reason' => $reason,
                'url' => $url,
            ]);

        $this->mailer->send($email);
    }

    private function getUserDisplayName(User $user): string
    {
        $fullName = trim(sprintf(
            '%s %s',
            $user->getFirstName() ?? '',
            $user->getLastName() ?? '',
        ));

        if ($fullName !== '') {
            return $fullName;
        }

        return (string) $user->getEmail();
    }
}