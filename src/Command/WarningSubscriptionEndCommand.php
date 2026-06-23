<?php

namespace App\Command;

use App\Controller\App\DashboardAppController;
use App\Controller\App\UserAppCrudController;
use App\Repository\CommentRepository;
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
use Symfony\Component\Mime\Email;

class WarningSubscriptionEndCommand extends Command
{
    private $userRepository;
    private $mailer;
    private $adminUrlGenerator;
    private EntityManagerInterface $entityManager;

    protected static $defaultName = 'app:subscription:warning';

    public function __construct(UserRepository $userRepository, MailerInterface $mailer, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager)
    {
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Deletes rejected and spam comments from the database')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $users = $this->userRepository->findAll();

        foreach ($users as $user) {
            $sub = $user->getSubscription();
            if (!$sub) {
                continue;
            }
            
            if (!$sub->isWarningMail()) {
                continue;
            }

            if ($sub->getWarningMailSentAt() !== null) {
                continue;
            }

            $end = $sub->getSubscriptionEnd();
            $plan = $sub->getPlan();
            $planName = $plan?->getName() ?? 'Free';

            $subject = 'Votre abonnement Mileo arrive à expiration';
            $template = 'Emails/subscriptionWarning.html.twig';

            // "Pro Annuel", "Pro Mensuel", "FREE"
            $normalized = mb_strtolower(trim($planName));

            if (str_contains($normalized, 'pro') && (str_contains($normalized, 'annuel') || str_contains($normalized, 'année') || str_contains($normalized, 'year'))) {
                $subject = 'Votre abonnement Mileo Pro arrive à expiration';
                $template = 'Emails/subscriptionWarning_pro_yearly.html.twig';
            } else {
                $subject = 'Votre compte Mileo FREE arrive à expiration';
                $template = 'Emails/subscriptionWarning_free.html.twig';
            }

            $url = $this->adminUrlGenerator
                ->setDashboard(DashboardAppController::class)
                ->setController(UserAppCrudController::class)
                ->setAction('subscriptionForm')
                ->generateUrl();

            $url = str_replace("http", "https", $url);
            $url = str_replace("localhost", $_ENV["APP_PUBLIC_URL"], $url);

            if ($dryRun) {
                $io->info(sprintf('[DRY RUN] %s -> %s (%s)', $user->getEmail(), $template, $planName));
                continue;
            }

            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate($template)
                ->context([
                    'user' => $user,
                    'url'  => $url,
                    'plan' => $plan,
                    'expiresAt' => $end,
                ]);

            $this->mailer->send($email);

            $sub->setWarningMailSentAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $io->info(sprintf('Mail envoyé (%s) à %s', $planName, $user->getEmail()));
        }

        $io->success("Fin de tâche");
        return Command::SUCCESS;
    }
}