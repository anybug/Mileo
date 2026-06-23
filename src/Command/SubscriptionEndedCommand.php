<?php

namespace App\Command;

use App\Controller\App\DashboardAppController;
use App\Controller\App\UserAppCrudController;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;

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
            ->setDescription('Envoi un email aux utilisateurs dont l’abonnement est expiré (non valide).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ne pas envoyer, afficher seulement')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');

        $users = $this->userRepository->findAll();
        $sent = 0;

        foreach ($users as $user) {
            $subscription = $user->getSubscription();
            if (!$subscription) {
                continue;
            }

            // On ne mail que les abonnements non valides (expirés)
            if ($subscription->isValid()) {
                continue;
            }

            if ($subscription->getWarningMailSentAt() !== null) {
                continue;
            }

            $end = $subscription->getSubscriptionEnd();

            // URL de renouvellement
            $url = $this->adminUrlGenerator
                ->setDashboard(DashboardAppController::class)
                ->setController(UserAppCrudController::class)
                ->setAction('subscriptionForm')
                ->generateUrl();

            $url = str_replace('http', 'https', $url);
            $url = str_replace('localhost', $_ENV['APP_PUBLIC_URL'] ?? 'localhost', $url);

            // Sujet (diff selon plan)
            $planName = $subscription->getPlan()?->getName() ?? 'Free';

            $subject = 'Votre abonnement Mileo est arrivé à expiration';

            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->bcc($_ENV['ADMIN_EMAIL'])
                ->subject($subject)
                ->htmlTemplate('Emails/subscriptionExpired.html.twig')
                ->context([
                    'user' => $user,
                    'url' => $url,
                    'planName' => $planName,
                    'expiredAt' => $end,
                ]);

            if ($dryRun) {
                $io->info(sprintf('[DRY RUN] %s (expired at %s)', $user->getEmail(), $end->format('Y-m-d')));
                continue;
            }

            $this->mailer->send($email);
            $subscription->setExpiredMailSentAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            
            $io->info(sprintf('Email sent to %s (expired at %s)', $user->getEmail(), $end->format('Y-m-d')));
            $sent++;
        }

        $io->success($dryRun ? 'Dry-run terminé.' : sprintf('Terminé. %d email(s) envoyé(s).', $sent));
        return Command::SUCCESS;
    }
}
