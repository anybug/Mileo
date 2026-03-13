<?php
// src/EventSubscriber/UserFirstLoginSubscriber.php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\Subscription;
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
            UserFirstSubscriptionEvent::class => 'onFirstSubscrption',
        ];
    }

    public function onFirstSubscrption(UserFirstSubscriptionEvent $event): void
    {
        $user = $event->getUser();

        // Si l’utilisateur a déjà une subscription, on ne fait rien
        if ($user->getSubscription()) {
            return;
        }

        // Plan gratuit
        $plan = $this->em->getRepository(Plan::class)
            ->findOneBy(['price_per_year' => 0]);

        if (!$plan) {
            // Rien en base => on ne casse pas la connexion
            return;
        }

        // Subscription gratuite
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setSubscriptionStart(new \DateTime());
        $subscription->setSubscriptionEnd(new \DateTime('+'.$plan->getPlanPeriod().' month'));
        $user->setSubscription($subscription);

        // Order gratuit
        $order = new Order();
        $order->setUser($user);
        $order->setPlan($plan);
        $order->setCreatedAt(new \DateTime());
        $order->setUpdatedAt(new \DateTime());
        $order->setProductName($plan->getName());
        $order->setProductDescription($plan->getPlanDescription());
        $order->setVatAmount($plan->getTotalCost());
        $order->setTotalHt($plan->getTotalCost());
        $order->setSubscriptionEnd($subscription->getSubscriptionEnd());
        $order->setStatus('new');

        $this->em->persist($subscription);
        $this->em->persist($order);
        $this->em->flush();

    }

}
