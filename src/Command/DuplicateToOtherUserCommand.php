<?php

namespace App\Command;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\User;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Service\TripDuplicationService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(name: 'app:duplication:user')]
class DuplicateToOtherUserCommand extends Command
{
    private $userRepository;
    private $duplicator;
    private $reportRepository;
    private $entityManager;
    private $dispatcher;

    public function __construct(
        UserRepository $userRepository,
        ReportRepository $reportRepository,
        TripDuplicationService $duplicator,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->reportRepository = $reportRepository;
        $this->duplicator = $duplicator;
        $this->entityManager = $entityManager;
        $this->dispatcher = $dispatcher;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Duplique le rapport d\'un user à un autre.')
            ->addArgument('username', InputArgument::REQUIRED, 'The username of the target user.')
            ->addArgument('source', InputArgument::REQUIRED, 'The report_id source.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $source = (int)$input->getArgument('source');

        $user = $this->userRepository->findOneByEmail($username);
        if (!$user) {
            $io->error(sprintf('Utilisateur "%s" non trouvé.', $username));
            return Command::FAILURE;
        }

        $report = $this->reportRepository->find($source);
        if (empty($report)) {
            $io->warning(sprintf('Aucun rapport trouvé pour l\'id %d.', $source));
            return Command::SUCCESS;
        }

        /*if (!$dryRun && !$io->confirm(sprintf('Confirmez-vous la duplication de %d rapport(s) de %d vers %d ?', count($reports), $source, $target), false)) {
            $io->info('Opération annulée.');
            return Command::SUCCESS;
        }*/

        $count = 0;

        try {
            $newReport = $this->duplicateReport($report, $user);
            $count++;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la duplication du rapport %d : %s', $report->getId(), $e->getMessage()));
        }

        $io->success(sprintf('Terminé. %d rapport(s) dupliqué(s).', $count));

        return Command::SUCCESS;
    }

    private function duplicateReport(Report $sourceReport, User $targetUser): void
    {
        $newReport = new Report();
        $newReport->setStartDate($sourceReport->getStartDate());
        $newReport->setEndDate($sourceReport->getEndDate());
        $newReport->setUser($targetUser);
        $newReport->calculateKm();
        $newReport->calculateTotal();
        $this->entityManager->persist($newReport);
        $this->entityManager->flush();

        $vehicule = $targetUser->getDefaultVehicule();

        /* Clone des lignes */
        foreach ($sourceReport->getLines() as $line) 
        {
            $newLine = new ReportLine();
            // dd($original->getLines());
            $newLine->setKm($line->getKm());
            $newLine->setIsReturn($line->getIsReturn());
            $newLine->setKmTotal($line->getKmTotal());
            $newLine->setAmount($line->getAmount());
            $newLine->setStartAdress($line->getStartAdress());
            $newLine->setEndAdress($line->getEndAdress());
            $newLine->setComment($line->getComment());
            $newLine->setVehicule($vehicule);
            $newLine->setTravelDate($line->getTravelDate());
            $scale = $newLine->getVehicule()->getScale();
            $newLine->setScale($scale);
            $newLine->calculateAmount();
            $newLine->setReport($newReport);

            $this->entityManager->persist($newLine);
            $this->entityManager->flush();
                        
        }

        if ($this->entityManager->contains($newReport)) {
            $this->entityManager->refresh($newReport); 
        }

        $this->dispatcher->dispatch(new AfterEntityUpdatedEvent($newReport));

    }
}
