<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'subscription')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /** Le plan souscrit : FREE, PRO, TEAM, CABINET. */
    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plan $plan = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $subscription_start;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $subscription_end;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $trialEndsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $warningMailThirtyDaysSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $warningMailSevenDaysSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiredMailSentAt = null;

    public function __construct()
    {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }
    
    public function __toString()
    {
        return $this->getPlan()->getName();
    }
   
    /** L'abonnement est-il valide à l'instant donné (par défaut : maintenant) ? */
    public function isValid(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        return $at >= $this->getSubscriptionStart() && $at < $this->getSubscriptionEnd();
    }
    
    public function isWarning()
    {
        $now = new \DateTime("now");
        $warning = new \DateTime($this->getSubscriptionEnd()->format("Y-m-d"));
        $warning->modify('-1 month');
        if($now > $warning && $this->isValid())
        {
            return true;
        }

        return false;
    }
    
    public function isWarningMail()
    {
        $now = new \DateTime("now");
        $date = new \DateTime($now->format("Y-m-d"));
        $warning = new \DateTime($this->getSubscriptionEnd()->format("Y-m-d"));
        $warning->modify('-7 days');
        if($date == $warning && $this->isValid())
        {
            return true;
        }

        return false;
    }

    public function getNumberDays(){
        $now = time(); // or your date as well
        $your_date = strtotime($this->getSubscriptionEnd()->format("Y-m-d"));
        $datediff = $your_date - $now;
        //dd($this->getNumberDays());
        return( round($datediff / (60 * 60 * 24)));
    }

    public function getProgressValue(){

        $value = $this->getNumberDays()/360*100 ;
        return $value;
    }

    /** TODO: En essai ACTIF (à distinguer d'un essai expiré : isTrial true mais isValid false). */
    /*public function isInTrial(?\DateTimeImmutable $at = null): bool
    {
        return $this->isTrial && $this->isValid($at);
    }*/

    /** Nombre de jours avant expiration (négatif si déjà expiré). Pratique pour les nudges. */
    public function daysUntilExpiry(?\DateTimeImmutable $at = null): int
    {
        $at ??= new \DateTimeImmutable();

        return (int) $at->diff($this->getSubscriptionEnd())->format('%r%a');
    }

    public function getSubscriptionStart(): ?\DateTimeImmutable
    {
        return $this->subscription_start;
    }

    public function setSubscriptionStart(\DateTimeImmutable $subscription_start): static
    {
        $this->subscription_start = $subscription_start;

        return $this;
    }

    public function getSubscriptionEnd(): ?\DateTimeImmutable
    {
        return $this->subscription_end;
    }

    public function setSubscriptionEnd(\DateTimeImmutable $subscription_end): static
    {
        $this->subscription_end = $subscription_end;

        return $this;
    }

    public function getExpiredMailSentAt(): ?\DateTimeImmutable
    {
        return $this->expiredMailSentAt;
    }

    public function setExpiredMailSentAt(?\DateTimeImmutable $expiredMailSentAt): static
    {
        $this->expiredMailSentAt = $expiredMailSentAt;

        return $this;
    }

    public function getWarningMailThirtyDaysSentAt(): ?\DateTimeImmutable
    {
        return $this->warningMailThirtyDaysSentAt;
    }

    public function setWarningMailThirtyDaysSentAt(?\DateTimeImmutable $warningMailThirtyDaysSentAt): static
    {
        $this->warningMailThirtyDaysSentAt = $warningMailThirtyDaysSentAt;

        return $this;
    }

    public function getWarningMailSevenDaysSentAt(): ?\DateTimeImmutable
    {
        return $this->warningMailSevenDaysSentAt;
    }

    public function setWarningMailSevenDaysSentAt(?\DateTimeImmutable $warningMailSevenDaysSentAt): static
    {
        $this->warningMailSevenDaysSentAt = $warningMailSevenDaysSentAt;

        return $this;
    }

    public function getTrialEndsAt(): ?\DateTimeImmutable
    {
        return $this->trialEndsAt;
    }

    public function setTrialEndsAt(?\DateTimeImmutable $trialEndsAt): static
    {
        $this->trialEndsAt = $trialEndsAt;

        return $this;
    }

    public function isTrial(): bool
    {
        return null !== $this->trialEndsAt;
    }

    public function isInTrial(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        return $this->isTrial()
            && $this->isValid($at)
            && $at < $this->trialEndsAt;
    }
}
