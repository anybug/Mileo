<?php

namespace App\Command;

use App\Controller\App\DashboardAppController;
use App\Controller\App\UserAppCrudController;
use App\Entity\Subscription;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class SubscriptionEndedCommand extends Command
{
    protected static $defaultName = 'app:subscription:ended';
    private UserRepository $userRepository;
    private MailerInterface $mailer;
    private AdminUrlGenerator $adminUrlGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(
        UserRepository $userRepository,
        MailerInterface $mailer,
        AdminUrlGenerator $adminUrlGenerator,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();

        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Envoie un e-mail aux utilisateurs dont l’abonnement ou la période d’essai est expiré.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Ne pas envoyer, afficher seulement'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTimeImmutable();
        $url = $this->createSubscriptionUrl();

        $users = $this->userRepository->findAll();

        $sent = 0;
        $errors = 0;

        foreach ($users as $user) {
            $subscription = $user->getSubscription();

            if (null === $subscription) {
                continue;
            }

            if (!$this->isExpired($subscription, $now)) {
                continue;
            }

            if (null !== $subscription->getExpiredMailSentAt()) {
                continue;
            }

            $isTrial = $subscription->isTrial();

            $expiredAt = $isTrial
                ? $subscription->getTrialEndsAt()
                : $subscription->getSubscriptionEnd();

            if (null === $expiredAt) {
                continue;
            }

            $planName = $subscription->getPlan()?->getName() ?? 'Free';

            $subject = $isTrial
                ? 'Votre période d’essai Mileo est terminée'
                : 'Votre abonnement Mileo est arrivé à expiration';

            $template = $isTrial
                ? 'Emails/trialExpired.html.twig'
                : 'Emails/subscriptionExpired.html.twig';

            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate($template)
                ->context([
                    'user' => $user,
                    'url' => $url,
                    'planName' => $planName,
                    'expiredAt' => $expiredAt,
                    'isTrial' => $isTrial,
                ]);

            $adminEmail = trim((string) ($_ENV['ADMIN_EMAIL'] ?? ''));

            if ('' !== $adminEmail) {
                $email->bcc(new Address($adminEmail));
            }

            if ($dryRun) {
                if ($isTrial) {
                    $io->warning(sprintf(
                        '[DRY RUN - FIN D’ESSAI] E-mail "%s" qui serait envoyé à %s | essai terminé le %s | template : %s',
                        $subject,
                        $user->getEmail(),
                        $expiredAt->format('Y-m-d'),
                        $template
                    ));
                } else {
                    $io->info(sprintf(
                        '[DRY RUN - ABONNEMENT EXPIRÉ] E-mail "%s" qui serait envoyé à %s | abonnement terminé le %s | template : %s',
                        $subject,
                        $user->getEmail(),
                        $expiredAt->format('Y-m-d'),
                        $template
                    ));
                }

                continue;
            }

            try {
                $this->mailer->send($email);

                $subscription->setExpiredMailSentAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                ++$sent;

                $io->info(sprintf(
                    '%s expiré : e-mail envoyé à %s.',
                    $isTrial ? 'Essai' : 'Abonnement',
                    $user->getEmail()
                ));
            } catch (TransportExceptionInterface $exception) {
                ++$errors;

                $io->error(sprintf(
                    'Impossible d’envoyer l’e-mail à %s : %s',
                    $user->getEmail(),
                    $exception->getMessage()
                ));
            }
        }

        $io->success($dryRun
            ? 'Dry-run terminé.'
            : sprintf('Terminé. %d e-mail(s) envoyé(s), %d erreur(s).', $sent, $errors)
        );

        return $errors > 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    private function isExpired(
        Subscription $subscription,
        \DateTimeImmutable $now
    ): bool {
        if ($subscription->isTrial()) {
            $trialEndsAt = $subscription->getTrialEndsAt();

            return null !== $trialEndsAt && $trialEndsAt <= $now;
        }

        $subscriptionEnd = $subscription->getSubscriptionEnd();

        return null !== $subscriptionEnd && $subscriptionEnd <= $now;
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
