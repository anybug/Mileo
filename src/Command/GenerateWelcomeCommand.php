<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Event\UserFirstLoginEvent;
use App\Event\UserFirstSubscriptionEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(name: 'app:generate:welcome')]
class GenerateWelcomeCommand extends Command
{
    private $userRepository;
    private $entityManager;
    private $dispatcher;

    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->dispatcher = $dispatcher;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Génère le package de bienvenue manuellement en cas d\'erreur passée.')
            ->addArgument('username', InputArgument::REQUIRED, 'The username of the target user.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');

        $user = $this->userRepository->findOneByEmail($username);
        if (!$user) {
            $io->error(sprintf('Utilisateur "%s" non trouvé.', $username));
            return Command::FAILURE;
        }

        $count = 0;

        try {
            $welcome = $this->generateWelcome($user);
            $count++;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la création du package pour: %s', $user->getUsername(), $e->getMessage()));
        }

        $io->success(sprintf('Terminé. %d package généré(s).', $count));

        return Command::SUCCESS;
    }

    private function generateWelcome(User $user): void
    {

        //auto subscription
        if (!$user->getSubscription()) {
            $this->dispatcher->dispatch(new UserFirstSubscriptionEvent($user));
        }

        //email de bienvenue
        $this->dispatcher->dispatch(new UserFirstLoginEvent($user));

    }
}
