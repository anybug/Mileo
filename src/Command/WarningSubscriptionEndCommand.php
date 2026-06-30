<?php

namespace App\Command;

use App\Controller\App\DashboardAppController;
use App\Controller\App\UserAppCrudController;
use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'app:subscription:warning',
    description: 'Send subscription and trial expiry warnings at D-30 and D-7.'
)]
final class WarningSubscriptionEndCommand extends Command
{
    private const WARNING_DAYS = [30, 7];

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly MailerInterface $mailer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Display the emails that would be sent without sending them.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $today = new \DateTimeImmutable('today');
        $subscriptionUrl = $this->createSubscriptionUrl();

        $sentCount = 0;
        $errorCount = 0;

        foreach (self::WARNING_DAYS as $daysUntilExpiry) {
            $subscriptions = $this->subscriptionRepository->findSubscriptionsToWarn(
                $daysUntilExpiry,
                $today
            );

            $io->section(sprintf(
                'D-%d: %d subscription(s) found.',
                $daysUntilExpiry,
                count($subscriptions)
            ));

            foreach ($subscriptions as $subscription) {
                $user = $subscription->getUser();
                $plan = $subscription->getPlan();

                if (null === $user || null === $plan) {
                    continue;
                }

                $mailDefinition = $this->resolveMailDefinition(
                    $subscription,
                    $daysUntilExpiry
                );

                if (null === $mailDefinition) {
                    continue;
                }

                [$subject, $template] = $mailDefinition;

                $expiresAt = $subscription->isTrial()
                    ? $subscription->getTrialEndsAt()
                    : $subscription->getSubscriptionEnd();

                $warningType = $subscription->isTrial()
                    ? 'trial'
                    : 'subscription';

                if ($dryRun) {
                    $io->info(sprintf(
                        '[DRY RUN] %s D-%d: %s -> %s',
                        ucfirst($warningType),
                        $daysUntilExpiry,
                        $user->getEmail(),
                        $template
                    ));

                    continue;
                }

                try {
                    $email = (new TemplatedEmail())
                        ->to(new Address($user->getEmail()))
                        ->subject($subject)
                        ->htmlTemplate($template)
                        ->context([
                            'user' => $user,
                            'url' => $subscriptionUrl,
                            'plan' => $plan,
                            'expiresAt' => $expiresAt,
                            'daysUntilExpiry' => $daysUntilExpiry,
                            'isTrial' => $subscription->isTrial(),
                        ]);

                    $this->mailer->send($email);

                    $this->markWarningAsSent($subscription, $daysUntilExpiry);
                    $this->entityManager->flush();

                    ++$sentCount;

                    $io->success(sprintf(
                        '%s D-%d warning sent to %s.',
                        ucfirst($warningType),
                        $daysUntilExpiry,
                        $user->getEmail()
                    ));
                } catch (TransportExceptionInterface $exception) {
                    ++$errorCount;

                    $io->error(sprintf(
                        'Unable to send the %s D-%d warning to %s: %s',
                        $warningType,
                        $daysUntilExpiry,
                        $user->getEmail(),
                        $exception->getMessage()
                    ));
                }
            }
        }

        $io->success(sprintf(
            'Task completed. %d email(s) sent, %d error(s).',
            $sentCount,
            $errorCount
        ));

        return $errorCount > 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    private function markWarningAsSent(
        Subscription $subscription,
        int $daysUntilExpiry
    ): void {
        $sentAt = new \DateTimeImmutable();

        match ($daysUntilExpiry) {
            30 => $subscription->setWarningMailThirtyDaysSentAt($sentAt),
            7 => $subscription->setWarningMailSevenDaysSentAt($sentAt),
            default => throw new \InvalidArgumentException('Unsupported warning delay.'),
        };
    }

    private function resolveMailDefinition(
        Subscription $subscription,
        int $daysUntilExpiry
    ): ?array {
        if ($subscription->isTrial()) {
            if (7 !== $daysUntilExpiry) {
                return null;
            }

            return [
                $this->translator->trans(
                    'Your Mileo trial period expires in %days% days.',
                    ['%days%' => $daysUntilExpiry]
                ),
                'Emails/subscriptionWarning_trial.html.twig',
            ];
        }

        $plan = $subscription->getPlan();

        if (null === $plan) {
            return null;
        }

        $normalizedPlanName = mb_strtolower(trim($plan->getName()));

        if (str_contains($normalizedPlanName, 'team')) {
            return [
                $this->translator->trans(
                    'Your Mileo Team subscription expires in %days% days.',
                    ['%days%' => $daysUntilExpiry]
                ),
                'Emails/subscriptionWarning_team.html.twig',
            ];
        }

        if (str_contains($normalizedPlanName, 'pro')) {
            return [
                $this->translator->trans(
                    'Your Mileo Pro subscription expires in %days% days.',
                    ['%days%' => $daysUntilExpiry]
                ),
                'Emails/subscriptionWarning_pro.html.twig',
            ];
        }

        return null;
    }

    private function createSubscriptionUrl(): string
    {
        $generatedUrl = $this->adminUrlGenerator
            ->setDashboard(DashboardAppController::class)
            ->setController(UserAppCrudController::class)
            ->setAction('subscriptionForm')
            ->generateUrl();

        $publicUrl = rtrim((string) ($_ENV['APP_PUBLIC_URL'] ?? ''), '/');

        if ('' === $publicUrl) {
            return $generatedUrl;
        }

        if (
            !str_starts_with($publicUrl, 'http://')
            && !str_starts_with($publicUrl, 'https://')
        ) {
            $publicUrl = 'https://' . $publicUrl;
        }

        $urlParts = parse_url($generatedUrl);

        if (false === $urlParts) {
            return $generatedUrl;
        }

        $path = $urlParts['path'] ?? '';
        $query = isset($urlParts['query'])
            ? '?' . $urlParts['query']
            : '';

        return $publicUrl . $path . $query;
    }
}