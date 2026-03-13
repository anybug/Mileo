<?php
// src/EventSubscriber/UserFirstLoginSubscriber.php

namespace App\EventSubscriber;

use App\Event\UserFirstLoginEvent;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class UserFirstLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserFirstLoginEvent::class => 'onFirstLogin',
        ];
    }

    public function onFirstLogin(UserFirstLoginEvent $event): void
    {
        $user = $event->getUser();

        // Mail de bienvenue
        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail()))
            ->subject('Bienvenue sur Mileo !')
            ->htmlTemplate('Emails/welcome.html.twig')
            ->context([
                'user' => $user,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Journaliser l'erreur
            $this->logger->error('Échec de l\'envoi de l\'email de bienvenue à ' . $user->getEmail(), [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);
            // Optionnel : déclencher un événement ou une action pour réessayer plus tard
        }
    }

}
