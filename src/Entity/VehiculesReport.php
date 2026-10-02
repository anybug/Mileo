<?php

namespace App\Entity;

use App\Repository\VehiculesReportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehiculesReportRepository::class)]
class VehiculesReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Report::class, inversedBy: 'vehiculesReports')]
    private $report;

    #[ORM\ManyToOne(targetEntity: Vehicule::class)]
    private $vehicule;
    
    #[ORM\ManyToOne(targetEntity: Scale::class)]
    private $scale;

    #[ORM\Column(type: 'integer')]
    private $km;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private $total;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function getVehicule(): ?Vehicule
    {
        return $this->vehicule;
    }

    public function setVehicule(?Vehicule $vehicule): self
    {
        $this->vehicule = $vehicule;

        return $this;
    }

    public function getScale(): ?Scale
    {
        return $this->scale;
    }

    public function setScale(?Scale $scale): self
    {
        $this->scale = $scale;

        return $this;
    }

    public function getKm(): ?int
    {
        return $this->km;
    }

    public function setKm(int $km): self
    {
        $this->km = $km;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function calculateTotal()
    {
        $km = 0;
        $total = 0;
        foreach ($this->getReport()->getLines() as $line) {
            if ($this->getVehicule() == $line->getVehicule()) {
                $km += $line->getKmTotal();
                $amount = ($this->getScale()->getRate()*$line->getKmTotal());
                $amount = round($amount, 2);
                $total += $amount;
            }
        }
        $grandTotal = $total + ($this->getScale()->getAmount()/12);
        //majoration de 20% pour les VE
        if($this->getVehicule()->isElectric()){
            $major = round($grandTotal * 0.20, 2);
            $grandTotal += $major;
        }
        $this->setKm($km);
        $this->setTotal($grandTotal);
    }
    
}
