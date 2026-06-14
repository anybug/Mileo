<?php
// src/EventSubscriber/UserFirstLoginSubscriber.php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Enum\PlanCode;
use App\Event\UserFirstSubscriptionEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserFirstSubscriptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserFirstSubscriptionEvent::class => 'onFirstSubscription',
        ];
    }

    public function onFirstSubscription(UserFirstSubscriptionEvent $event): void
    {
        $user = $event->getUser();

        // Si l’utilisateur a déjà une subscription, on ne fait rien
        if ($user->getSubscription()) {
            return;
        }

        // Plan gratuit
        $plan = $this->em->getRepository(Plan::class)->findByCode(PlanCode::FREE);

        if (!$plan) {
            // Rien en base => on ne casse pas la connexion
            return;
        }

        // Subscription gratuite
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setSubscriptionStart(new \DateTimeImmutable());
        $subscription->setSubscriptionEnd(new \DateTimeImmutable('+'.$plan->getPlanPeriod().' month'));
        $user->setSubscription($subscription);

        $this->em->persist($subscription);
        $this->em->flush();

    }

}
