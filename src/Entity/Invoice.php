<?php

namespace App\Entity;

use App\Entity\Order;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    public const TYPE_ORDER = 'order';
    public const TYPE_TEAM_REPORT = 'team_report';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $num;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 30, enumType: InvoiceStatus::class, nullable: true)]
    private ?InvoiceStatus $status = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $teamManager = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $billingYear = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $billingMonth = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalHt = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $vatAmount = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalTtc = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $facturXPath = null;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Report::class)]
    private Collection $reports;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\OneToOne(targetEntity: Order::class, inversedBy: 'invoice')]
    private $order;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $modificationReason = null;

    public function __construct()
    {
        $this->reports = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNum(): ?int
    {
        return $this->num;
    }

    public function setNum(?int $num): self
    {
        $this->num = $num;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getType(): string
    {
        return $this->type ?? self::TYPE_ORDER;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isOrderInvoice(): bool
    {
        return $this->getType() === self::TYPE_ORDER;
    }

    public function isTeamReportInvoice(): bool
    {
        return $this->getType() === self::TYPE_TEAM_REPORT;
    }

    public function getStatus(): ?InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(?InvoiceStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusAsString(): string
    {
        return $this->status?->value ?? InvoiceStatus::DRAFT->value;
    }

    public function setStatusAsString(string $statusAsString): self
    {
        $this->status = InvoiceStatus::from($statusAsString);

        return $this;
    }

    public function getStatusLabel(): string
    {
        return $this->status?->label() ?? InvoiceStatus::DRAFT->label();
    }

    public function getStatusBadgeClass(): string
    {
        return $this->status?->badgeClass() ?? InvoiceStatus::DRAFT->badgeClass();
    }

    public function canBeSent(): bool
    {
        return $this->status?->canBeSent() ?? false;
    }

    public function getTeamManager(): ?User
    {
        return $this->teamManager;
    }

    public function setTeamManager(?User $teamManager): self
    {
        $this->teamManager = $teamManager;

        return $this;
    }

    public function getBillingYear(): ?int
    {
        return $this->billingYear;
    }

    public function setBillingYear(?int $billingYear): self
    {
        $this->billingYear = $billingYear;

        return $this;
    }

    public function getBillingMonth(): ?int
    {
        return $this->billingMonth;
    }

    public function setBillingMonth(?int $billingMonth): self
    {
        $this->billingMonth = $billingMonth;

        return $this;
    }

    public function getTotalHt(): ?float
    {
        return $this->totalHt;
    }

    public function setTotalHt(?float $totalHt): self
    {
        $this->totalHt = $totalHt;

        return $this;
    }

    public function getVatAmount(): ?float
    {
        return $this->vatAmount;
    }

    public function setVatAmount(?float $vatAmount): self
    {
        $this->vatAmount = $vatAmount;

        return $this;
    }

    public function getTotalTtc(): ?float
    {
        return $this->totalTtc;
    }

    public function setTotalTtc(?float $totalTtc): self
    {
        $this->totalTtc = $totalTtc;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getFacturXPath(): ?string
    {
        return $this->facturXPath;
    }

    public function setFacturXPath(?string $facturXPath): self
    {
        $this->facturXPath = $facturXPath;

        return $this;
    }

    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(Report $report): self
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setInvoice($this);
        }

        return $this;
    }

    public function removeReport(Report $report): self
    {
        if ($this->reports->removeElement($report)) {
            if ($report->getInvoice() === $this) {
                $report->setInvoice(null);
            }
        }

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getBillingPeriod(): string
    {
        if ($this->billingYear === null || $this->billingMonth === null) {
            return '-';
        }

        $months = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];

        return sprintf(
            '%s %04d',
            $months[$this->billingMonth] ?? sprintf('%02d', $this->billingMonth),
            $this->billingYear,
        );
    }

    public function getModificationReason(): ?string
    {
        return $this->modificationReason;
    }

    public function setModificationReason(?string $modificationReason): static
    {
        $this->modificationReason = $modificationReason;

        return $this;
    }

}
