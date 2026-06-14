<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Table(name: '`order`')]
#[ORM\Entity(repositoryClass: OrderRepository::class)]
class Order
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'orders')]
    private $plan;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $productName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $productDescription;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $subscription_end;

    #[ORM\Column(type: 'float', nullable: true)]
    private $vatAmount = 0;

    #[ORM\Column(type: 'float', nullable: true)]
    private $totalHt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $billingName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $billingAddress;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $billingPostcode;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $billingCity;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\OneToOne(targetEntity: Invoice::class, mappedBy: 'order', cascade: ['persist'])]
    private $invoice;

    #[ORM\Column(type: 'string')]
    private $status;

    const STATUS_NEW = 'new';
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_REFUND = 'refund';
    const STATUS_FREE = 'free';

    public static function getStatuses()
    {
        return [
            self::STATUS_NEW,
            self::STATUS_PENDING,
            self::STATUS_PAID,
            self::STATUS_REFUND,
            self::STATUS_FREE
        ];
    }

    public static function getStatusesLabels()
    {
        return [
            'New',
            'Pending',
            'Paid',
            'Refund',
            'free'
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
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

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): self
    {
        $this->productName = $productName;

        return $this;
    }

    public function getProductDescription(): ?string
    {
        return $this->productDescription;
    }

    public function setProductDescription(string $productDescription): self
    {
        $this->productDescription = $productDescription;

        return $this;
    }

    public function getVatAmount(): ?float
    {
        return $this->vatAmount;
    }

    public function setVatAmount(float $vatAmount): self
    {
        $this->vatAmount = $vatAmount;

        return $this;
    }

    public function getTotalHt(): ?float
    {
        return $this->totalHt;
    }

    public function setTotalHt(float $totalHt): self
    {
        $this->totalHt = $totalHt;

        return $this;
    }

    public function getBillingName(): ?string
    {
        return $this->billingName;
    }

    public function setBillingName(string $billingName): self
    {
        $this->billingName = $billingName;

        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(string $billingAddress): self
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }

    public function getBillingPostcode(): ?string
    {
        return $this->billingPostcode;
    }

    public function setBillingPostcode(string $billingPostcode): self
    {
        $this->billingPostcode = $billingPostcode;

        return $this;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    public function setBillingCity(string $billingCity): self
    {
        $this->billingCity = $billingCity;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, $this->getStatuses())) {
            throw new \InvalidArgumentException("Invalid status");
        }

        $this->status = $status;

        return $this;
    }

    public function setSubscriptionEnd(\DateTimeInterface $subscription_end): self
    {
        $this->subscription_end = $subscription_end;

        return $this;
    }

    public function getSubscriptionEnd(): ?\DateTimeInterface
    {
        return $this->subscription_end;
    }
    
    public function __toString()
    {
        return $this->getProductName();
    }

    public function calculateVatAmount(){
        $this->vatAmount = $this->getTotalHt()*(20/100);
        return $this;
    }

    public function getTotalTTC(){
        return $this->getTotalHt()+$this->getVatAmount();
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $invoice->setOrder($this);
        $num = sprintf("%s%05d",$this->getCreatedAt()->format("ym"),$invoice->getId());
        $invoice->setNum(intval($num));
        $this->invoice = $invoice;
        
        return $this;
    }
   
    public function getInvoiceNum(){
        return $this->getInvoice()->getNum();
    }
}
