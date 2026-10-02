<?php

namespace App\EventListener;

use App\Entity\Order;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class OrderListener
{
    private $mailer;
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if($entity instanceof Order)
        {
            if ($entity->getStatus() == Order::STATUS_PAID) {
                $this->mailConfirmation($args);
            }
        }
    }
    
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Order) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $changeSet = $entityManager
            ->getUnitOfWork()
            ->getEntityChangeSet($entity);

        if (!isset($changeSet['status'])) {
            return;
        }

        [$oldStatus, $newStatus] = $changeSet['status'];

        if (
            $newStatus === Order::STATUS_PAID
            && $oldStatus !== Order::STATUS_PAID
        ) {
            $this->mailConfirmation($args);
        }
    }

    public function mailConfirmation(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Order) {
            return;
        }

        $user = $entity->getUser();

        if ($user === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail()))
            ->subject('Votre abonnement Mileo')
            ->htmlTemplate('Emails/order.html.twig')
            ->context([
                'order' => $entity,
            ]);

        if (!empty($_ENV['ADMIN_EMAIL'])) {
            $email->addBcc(
                new Address($_ENV['ADMIN_EMAIL'])
            );
        }

        $this->mailer->send($email);
    }

}
