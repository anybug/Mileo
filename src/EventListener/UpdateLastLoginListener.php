<?php
// src/EventListener/UpdateLastLoginListener.php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\Entity\User;

#[AsEventListener(event: InteractiveLoginEvent::class, method:'onInteractiveLogin')]
class UpdateLastLoginListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setLastLogin(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
