<?php

namespace App\Controller;

use App\Controller\App\DashboardAppController;
use App\Controller\App\OrderAppCrudController;
use App\Controller\App\UserAppCrudController;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Plan;
use App\Entity\Purchase;
use App\Entity\Referral;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\PlanCode;
use App\Service\ReferralCodeGenerator;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Payum\Core\Payum;
use Payum\Core\Request\GetHumanStatus;
use Payum\Stripe\Request\Api\CreatePlan;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentController extends AbstractController 
{
    #[Route(path: '/prepare-payment/{order_id}/checkout', name: 'payum_prepare_payment')]
    public function prepareAction(Payum $payum, int $order_id, EntityManagerInterface $manager)
    {
        $order = $manager->getRepository(Order::class)->find($order_id);
        $user = $this->getUser();
        $gatewayName = 'stripe_checkout_session';
        $storage = $payum->getStorage(Payment::class);
        $payment = $storage->create();
        $payment->setNumber(uniqid());
        $payment->setCurrencyCode('EUR');
        $payment->setTotalAmount($order->getPlan()->getPricePerYear()*(120/100)*100);
        $payment->setDescription($order->getPlan()->getBillingDetails());
        $payment->setClientId($user->getId());
        $payment->setClientEmail($user->getEmail());

        $storage->update($payment);

        $captureToken = $payum->getTokenFactory()->createCaptureToken(
            $gatewayName, 
            $payment, 
            'payum_payment_done', // the route to redirect after capture
            ['order_id' => $order_id],
        );
            
        return $this->redirect($captureToken->getTargetUrl());    
    }

    #[Route(path: '/payment-done/{order_id}', name: 'payum_payment_done')]
    public function done(
        AdminUrlGenerator $adminUrlGenerator,
        Request $request,
        Payum $payum,
        EntityManagerInterface $manager,
        ReferralCodeGenerator $referralCodeGenerator,
        int $order_id
    )
    {
        $token = $payum->getHttpRequestVerifier()->verify($request);
        $gateway = $payum->getGateway($token->getGatewayName());

        // You can invalidate the token, so that the URL cannot be requested any more:
        // $payum->getHttpRequestVerifier()->invalidate($token);

        // Once you have the token, you can get the payment entity from the storage directly. 
        //$identity = $token->getDetails();
        //$payment = $payum->getStorage($identity->getClass())->find($identity);
        // Or Payum can fetch the entity for you while executing a request (preferred).
        $gateway->execute($status = new GetHumanStatus($token));
        $payment = $status->getFirstModel();

        if ($payment->getDetails()["status"] == "succeeded") {

            /** @var User $user */
            $user = $this->getUser();

            $order = $manager
                ->getRepository(Order::class)
                ->find($order_id);

            if (!$order) {
                throw $this->createNotFoundException(
                    'Commande introuvable.'
                );
            }

            // Paiement déjà traité
            if ($order->getStatus() === 'paid') {
                $url = $adminUrlGenerator
                    ->setDashboard(DashboardAppController::class)
                    ->setController(OrderAppCrudController::class)
                    ->setAction('successPayment')
                    ->set('id', $order->getId())
                    ->generateUrl();

                return $this->redirect($url);
            }

            $plan = $order->getPlan();

            $isFirstProSubscription = !$user->hasAlreadySubscribedToPro();

            $subscription = $user->getSubscription();

            $now = new \DateTimeImmutable('now');

            /*
            * Première souscription :
            * on crée l'abonnement avant de l'utiliser.
            */
            if ($subscription === null) {
                $subscription = new Subscription();

                $subscription->setUser($user);
                $subscription->setSubscriptionStart($now);
            }

            $subscription->setPlan($plan);

            /*
            * Détermination du début du renouvellement.
            *
            * Abonnement expiré :
            * on repart d'aujourd'hui.
            *
            * Abonnement encore valide :
            * on ajoute la durée à la date de fin actuelle.
            */
            if (
                $subscription->getSubscriptionEnd() === null
                || $subscription->getSubscriptionEnd() < $now
            ) {
                $start = $now;
            } else {
                $start = $subscription->getSubscriptionEnd();
            }

            /*
            * Ajout de la durée normale du plan.
            */
            $end = $start->modify(
                '+' . $plan->getPlanPeriod() . ' month'
            );

            $subscription->setSubscriptionEnd($end);

            $subscription->resetWarningMails();
            $subscription->setExpiredMailSentAt(null);

            /*
            * ---------------------------------------
            * CODE DE PARRAINAGE
            * ---------------------------------------
            *
            * Lors du premier passage en PRO,
            * on génère un code de parrainage.
            */
            if (
                $isFirstProSubscription
                && $plan->getCode() === PlanCode::PRO
            ) {
                $referralCodeGenerator->assignTo($user);
            }

            /*
            * ---------------------------------------
            * PARRAINAGE
            * ---------------------------------------
            */
            $sponsor = $order->getReferralSponsor();

            if ($sponsor !== null) {
                $existingReferral = $manager
                    ->getRepository(Referral::class)
                    ->findOneBy([
                        'referredUser' => $user,
                    ]);

                if ($existingReferral === null) {
                    // Filleul +45 jours
                    $subscription->addFreeDays(45);

                    // Parrain +45 jours
                    $sponsorSubscription = $sponsor->getSubscription();

                    if ($sponsorSubscription !== null) {
                        $sponsorSubscription->addFreeDays(45);
                        $manager->persist($sponsorSubscription);
                    }

                    // Création réelle seulement après paiement
                    $referral = new Referral();

                    $referral
                        ->setSponsor($sponsor)
                        ->setReferredUser($user)
                        ->setOrder($order)
                        ->setRewardGranted(true)
                        ->setRewardGrantedAt(new \DateTimeImmutable());

                    $manager->persist($referral);
                }
            }

            /*
            * ---------------------------------------
            * FACTURE
            * ---------------------------------------
            */
            $invoice = new Invoice();

            $manager->persist($invoice);

            /*
            * ---------------------------------------
            * COMMANDE
            * ---------------------------------------
            */
            $order->setInvoice($invoice);
            $order->setStatus('paid');

            /*
            * Important :
            * on le fait APRÈS le +45 jours pour enregistrer
            * la vraie date de fin dans la commande.
            */
            $order->setSubscriptionEnd(
                $subscription->getSubscriptionEnd()
            );

            $manager->persist($order);

            /*
            * ---------------------------------------
            * ABONNEMENT
            * ---------------------------------------
            */
            $manager->persist($subscription);

            /*
            * ---------------------------------------
            * UTILISATEUR
            * ---------------------------------------
            */
            $user->setSubscription($subscription);

            

            $manager->persist($user);

            /*
            * Un seul flush à la fin.
            */
            $manager->flush();

            $url = $adminUrlGenerator
                ->setDashboard(DashboardAppController::class)
                ->setController(OrderAppCrudController::class)
                ->setAction('successPayment')
                ->set('id', $order->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        $url = $adminUrlGenerator
            ->setDashboard(DashboardAppController::class)
            ->setController(UserAppCrudController::class)
            ->setAction('index')
            
            ->generateUrl();

        return $this->redirect($url);
    }
}



