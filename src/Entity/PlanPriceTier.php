<?php

namespace App\Entity;

use App\Repository\PlanPriceTierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanPriceTierRepository::class)]
class PlanPriceTier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'priceTiers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plan $plan = null;

    #[ORM\Column]
    private int $minQuantity = 1;

    /** null = dernier palier, non borné. */
    #[ORM\Column(nullable: true)]
    private ?int $maxQuantity = null;

    /** Prix unitaire annuel en euros (chaîne numérique) ; null si quoteOnly. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $unitPriceYearly = null;

    /** Palier "sur devis" (ex. 25+ sièges) : pas de prix affiché, contact commercial. */
    #[ORM\Column]
    private bool $quoteOnly = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getMinQuantity(): int
    {
        return $this->minQuantity;
    }

    public function setMinQuantity(int $minQuantity): static
    {
        $this->minQuantity = $minQuantity;

        return $this;
    }

    public function getMaxQuantity(): ?int
    {
        return $this->maxQuantity;
    }

    public function setMaxQuantity(?int $maxQuantity): static
    {
        $this->maxQuantity = $maxQuantity;

        return $this;
    }

    public function getUnitPriceYearly(): ?string
    {
        return $this->unitPriceYearly;
    }

    public function setUnitPriceYearly(?string $unitPriceYearly): static
    {
        $this->unitPriceYearly = $unitPriceYearly;

        return $this;
    }

    public function isQuoteOnly(): bool
    {
        return $this->quoteOnly;
    }

    public function setQuoteOnly(bool $quoteOnly): static
    {
        $this->quoteOnly = $quoteOnly;

        return $this;
    }

    /**
     * Prix unitaire mensuel DÉRIVÉ (affichage uniquement), en euros (chaîne numérique).
     * null si quoteOnly. bcdiv reste exact sur la chaîne ; saisis des annuels
     * multiples de 12 pour un "/mois" rond (72.00 -> 6.00).
     */
    public function getUnitPriceMonthlyDisplay(): ?string
    {
        return $this->unitPriceYearly === null ? null : bcdiv($this->unitPriceYearly, '12', 2);
    }

    /** La quantité tombe-t-elle dans l'intervalle [min, max] de ce palier ? */
    public function matches(int $quantity): bool
    {
        return $quantity >= $this->minQuantity
            && ($this->maxQuantity === null || $quantity <= $this->maxQuantity);
    }
}
