<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Referral
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sponsor = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?User $referredUser = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\Column]
    private bool $rewardGranted = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rewardGrantedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSponsor(): ?User
    {
        return $this->sponsor;
    }

    public function setSponsor(User $sponsor): static
    {
        $this->sponsor = $sponsor;

        return $this;
    }

    public function getReferredUser(): ?User
    {
        return $this->referredUser;
    }

    public function setReferredUser(User $referredUser): static
    {
        $this->referredUser = $referredUser;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function isRewardGranted(): bool
    {
        return $this->rewardGranted;
    }

    public function setRewardGranted(bool $rewardGranted): static
    {
        $this->rewardGranted = $rewardGranted;

        return $this;
    }

    public function getRewardGrantedAt(): ?\DateTimeImmutable
    {
        return $this->rewardGrantedAt;
    }

    public function setRewardGrantedAt(
        ?\DateTimeImmutable $rewardGrantedAt
    ): static {
        $this->rewardGrantedAt = $rewardGrantedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}