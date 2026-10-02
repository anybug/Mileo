<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\PlanCode;
use App\Service\ReferralCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-referral-codes',
    description: 'Génère un code de parrainage pour les utilisateurs Pro qui n’en ont pas encore.'
)]
class GenerateReferralCodesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReferralCodeGenerator $referralCodeGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $users = $this->entityManager
            ->getRepository(User::class)
            ->findAll();

        $generated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // Déjà un code
            if ($user->getReferralCode() !== null) {
                $skipped++;
                continue;
            }

            $subscription = $user->getSubscription();

            // Pas d'abonnement
            if ($subscription === null) {
                $skipped++;
                continue;
            }

            // Pas de plan
            $plan = $subscription->getPlan();

            if ($plan === null) {
                $skipped++;
                continue;
            }

            // Pas un abonnement PRO
            if ($plan->getCode() !== PlanCode::PRO) {
                $skipped++;
                continue;
            }

            // Abonnement non valide
            if (!$user->hasValidSubscription()) {
                $skipped++;
                continue;
            }

            $this->referralCodeGenerator->assignTo($user);

            $generated++;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d code(s) de parrainage généré(s). %d utilisateur(s) ignoré(s).',
            $generated,
            $skipped
        ));

        return Command::SUCCESS;
    }
}