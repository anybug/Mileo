<?php

namespace App\Entity;

use App\Repository\VehiculeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as AppAssert;

#[ORM\Entity(repositoryClass: VehiculeRepository::class)]
class Vehicule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'vehicules')]
    private $user;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'vehicules')]
    #[Assert\NotBlank]
    private $brand;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Power::class, inversedBy: 'vehicules')]
    private $power;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private $type;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private $model;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $is_default;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Scale::class, inversedBy: 'reportlines')]
    private $scale;
    
    #[ORM\OneToMany(targetEntity: ReportLine::class, mappedBy: 'vehicule', cascade: ['remove'])]
    private $reportlines;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $is_electric = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $kilometres;

    const TYPE_CAR = 'Car';
    const TYPE_CYCLO = 'Cyclo';
    
    public static function getTypes()
    {
        return [
            self::TYPE_CAR,
            self::TYPE_CYCLO
        ];
    }

    public static function getTypesLabels()
    {
        return [
            'Car',
            'Two wheels'
        ];
    }

    public function __construct()
    {
        $this->reportlines = new ArrayCollection();
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

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getPower(): ?Power
    {
        return $this->power;
    }

    public function setPower(?Power $power): self
    {
        $this->power = $power;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType($type): self
    {
        if (!in_array($type, $this->getTypes())) {
            throw new \InvalidArgumentException("Invalid type");
        }

        $this->type = $type;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getIsDefault(): ?bool
    {
        return $this->is_default;
    }

    public function setIsDefault(?bool $is_default): self
    {
        $this->is_default = $is_default;

        return $this;
    }

    public function __toString()
    {
        $string = $this->getBrand() . ' ' . $this->getModel();
        $string .= $this->isElectric() ? ' Elec.' : '';

        return $string;
    }

    /**
     * @return Collection<int, ReportLine>
     */
    public function getReportlines(): Collection
    {
        return $this->reportlines;
    }

    public function addReportline(ReportLine $reportline): self
    {
        if (!$this->reportlines->contains($reportline)) {
            $this->reportlines[] = $reportline;
            $reportline->setVehicule($this);
        }

        return $this;
    }

    public function removeReportline(ReportLine $reportline): self
    {
        if ($this->reportlines->removeElement($reportline)) {
            // set the owning side to null (unless already changed)
            if ($reportline->getVehicule() === $this) {
                $reportline->setVehicule(null);
            }
        }

        return $this;
    }

    public function isIsDefault(): ?bool
    {
        return $this->is_default;
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

    public function hasLatestScale()
    {
        $year = $this->getScale()->getYear();
        $years = [];

        foreach($this->getPower()->getScales() as $s)
        {
            $years[$s->getYear()][] = $s;
        }

        ksort($years);
        
        $latest_year = array_key_last($years);

        return $year == $latest_year ? true : false;
    }

    public function getKilometres(): ?int
    {
        return $this->kilometres;
    }

    public function setKilometres(?int $kilometres): static
    {
        $this->kilometres = $kilometres;

        return $this;
    }

    public function isElectric(): ?bool
    {
        return $this->is_electric;
    }

    public function isIsElectric(): ?bool
    {
        return $this->is_electric;
    }

    public function getIsElectric(): ?bool
    {
        return $this->is_electric;
    }

    public function setIsElectric(?bool $is_electric): static
    {
        $this->is_electric = $is_electric;

        return $this;
    }

}
