<?php

namespace App\Command;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\User;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Service\TripDuplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:duplication:year')]
class DuplicateWholeYearCommand extends Command
{
    private $userRepository;
    private $duplicator;
    private $reportRepository;
    private $entityManager;

    public function __construct(
        UserRepository $userRepository,
        ReportRepository $reportRepository,
        TripDuplicationService $duplicator,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->reportRepository = $reportRepository;
        $this->duplicator = $duplicator;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Duplique une année entière de rapports.')
            ->addArgument('username', InputArgument::REQUIRED, 'The username of the user.')
            ->addArgument('source', InputArgument::REQUIRED, 'The reports source year.')
            ->addArgument('target', InputArgument::REQUIRED, 'The reports target year.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la duplication sans appliquer les changements.');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $username = $input->getArgument('username');
        $source = (int)$input->getArgument('source');
        $target = $input->getArgument('target');

        if ($source <= 0 || $target <= 0) {
            $io->error('Les années source et cible doivent être des valeurs positives.');
            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneByEmail($username);
        if (!$user) {
            $io->error(sprintf('Utilisateur "%s" non trouvé.', $username));
            return Command::FAILURE;
        }

        $reports = $this->reportRepository->findByYear($source, $user);
        if (empty($reports)) {
            $io->warning(sprintf('Aucun rapport trouvé pour l\'année %d pour l\'utilisateur "%s".', $source, $username));
            return Command::SUCCESS;
        }

        /*if (!$dryRun && !$io->confirm(sprintf('Confirmez-vous la duplication de %d rapport(s) de %d vers %d ?', count($reports), $source, $target), false)) {
            $io->info('Opération annulée.');
            return Command::SUCCESS;
        }*/

        $count = 0;
        foreach ($reports as $report) {
            if ($dryRun) {
                $io->info(sprintf('[DRY RUN] Rapport %d (année %d) serait dupliqué vers %d.', $report->getId(), $source, $target));
                continue;
            }

            try {
                $targetMonth = $report->getStartDate()->format('m');
                $newReport = $this->duplicator->duplicateReport($report, $target.'-'.$targetMonth);
                $count++;
            } catch (\Exception $e) {
                $io->error(sprintf('Erreur lors de la duplication du rapport %d : %s', $report->getId(), $e->getMessage()));
                continue;
            }
        }

        $io->success(sprintf('Terminé. %d rapport(s) dupliqué(s).', $count));

        return Command::SUCCESS;
    }

}
