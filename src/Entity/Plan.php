<?php

namespace App\Entity;

use App\Enum\PlanCode;
use App\Enum\PricingModel;
use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(length: 100)]
    private string $name;

    /** Clé stable pour brancher la logique applicative (ne JAMAIS s'appuyer sur le name). */
    #[ORM\Column(length: 50, enumType: PlanCode::class, unique: true)]
    private PlanCode $code;

    /** Indique au calculateur s'il multiplie le prix par la quantité (PER_UNIT) ou non (FLAT). */
    #[ORM\Column(enumType: PricingModel::class)]
    private PricingModel $pricingModel;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Libellé de l'unité facturée, pour l'affichage : "compte", "siège", "client". */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $unitLabel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price_per_year = null;

    /** Prix barré promo (override d'affichage manuel), en euros (DECIMAL). */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $old_price_per_year = null;

    #[ORM\Column(type: Types::INTEGER, length: 5)]
    private int $plan_period;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $billingDetails = null;

    #[ORM\Column]
    private int $displayOrder = 0;

    #[ORM\Column]
    private bool $isPublished = true;

    /** @var Collection<int, PlanPriceTier> */
    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: PlanPriceTier::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['minQuantity' => 'ASC'])]
    private Collection $priceTiers;

    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'plan')]
    private Collection $subscriptions;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'plan')]
    private Collection $orders;

    public function __construct()
    {
        $this->priceTiers = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString()
    {
        return $this->getBillingDetails();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUnitLabel(): ?string
    {
        return $this->unitLabel;
    }

    public function setUnitLabel(?string $unitLabel): static
    {
        $this->unitLabel = $unitLabel;

        return $this;
    }

    public function getPricePerYear(): ?string
    {
        return $this->price_per_year;
    }

    public function setPricePerYear(?string $price_per_year): static
    {
        $this->price_per_year = $price_per_year;

        return $this;
    }

    public function getOldPricePerYear(): ?string
    {
        return $this->old_price_per_year;
    }

    public function setOldPricePerYear(?string $old_price_per_year): static
    {
        $this->old_price_per_year = $old_price_per_year;

        return $this;
    }

    public function getPlanPeriod(): ?int
    {
        return $this->plan_period;
    }

    public function setPlanPeriod(int $plan_period): static
    {
        $this->plan_period = $plan_period;

        return $this;
    }

    public function getBillingDetails(): ?string
    {
        return $this->billingDetails;
    }

    public function setBillingDetails(?string $billingDetails): static
    {
        $this->billingDetails = $billingDetails;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function isIsPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    /**
     * @return Collection<int, PlanPriceTier>
     */
    public function getPriceTiers(): Collection
    {
        return $this->priceTiers;
    }

    public function addPriceTier(PlanPriceTier $priceTier): static
    {
        if (!$this->priceTiers->contains($priceTier)) {
            $this->priceTiers->add($priceTier);
            $priceTier->setPlan($this);
        }

        return $this;
    }

    public function removePriceTier(PlanPriceTier $priceTier): static
    {
        if ($this->priceTiers->removeElement($priceTier)) {
            // set the owning side to null (unless already changed)
            if ($priceTier->getPlan() === $this) {
                $priceTier->setPlan(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setPlan($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // set the owning side to null (unless already changed)
            if ($subscription->getPlan() === $this) {
                $subscription->setPlan(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setPlan($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getPlan() === $this) {
                $order->setPlan(null);
            }
        }

        return $this;
    }

    public function getCode(): ?PlanCode
    {
        return $this->code;
    }

    public function setCode(PlanCode $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getPricingModel(): ?PricingModel
    {
        return $this->pricingModel;
    }

    public function setPricingModel(PricingModel $pricingModel): static
    {
        $this->pricingModel = $pricingModel;

        return $this;
    }


}
