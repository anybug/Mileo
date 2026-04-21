<?php

namespace App\Entity;

use App\Entity\ReportLine;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Repository\ReportRepository;
use App\Repository\ScaleRepository;
use App\Validator\Constraints as AppAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[AppAssert\NewReport(groups: ["new"])]
#[AppAssert\Report(groups: ["edit"])]

class Report
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reports')]
    private $user;

    private $scale;

    #[ORM\Column(type: 'date')]
    private $start_date;

    #[ORM\Column(type: 'date')]
    private $end_date;

    #[ORM\Column(type: 'date', nullable: true)]
    private $validate_date;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $km;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private $total;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\OneToMany(targetEntity: ReportLine::class, orphanRemoval: true, cascade: ['persist', 'remove'], mappedBy: 'report')]
    private $lines;

    private $year;
    private $month;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\OneToMany(targetEntity: VehiculesReport::class, orphanRemoval: true, cascade: ['persist', 'remove'], mappedBy: 'report')]
    private $vehiculesReports;

    /**
     * Constructor
     */
    public function __construct() {
        $this->lines = new ArrayCollection();
        $this->year = new \DateTime();
        $this->vehiculesReports = new ArrayCollection();
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

    public function getScale()
    {
        return $this->scale;
    }

    public function setScale($scale)
    {
        $this->scale = $scale;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(\DateTimeInterface $start_date): self
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEndDate(\DateTimeInterface $end_date): self
    {
        $this->end_date = $end_date;

        return $this;
    }

    public function calculateTotal()
    {
        $total=0;
        foreach ($this->getVehiculesReports() as $vr) {
            $total += $vr->getTotal();
        }

        $this->setTotal($total);

        return $this;
    }
    
    public function calculateKm(){
        $km=0;
        foreach ($this->getVehiculesReports() as $vr) {
            $km += $vr->getKm();
        }
        $this->setKm($km);
        
        return $this;
    }    

    /**
     * Add lines
     *
     * @param App\Entity\ReportLine $line
     * @return Report
     */
    public function addLine(ReportLine $line) : self 
    {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setReport($this);
        }

        return $this;

    }

    /**
     * Remove lines
     *
     * @param App\Entity\ReportLine $line
     */
    public function removeLine(ReportLine $line) : self  
    {
        $this->lines->removeElement($line);
        return $this;
    }

    /**
     * Get lines
     *
     * @return Collection|ReportLine[]
     */
    public function getLines() : Collection 
    {
        //tri par année
        $sort = Criteria::create()
            ->orderBy(['travel_date' => Order::Ascending]);
        //$sortedLines = $this->lines->matching($sort);
        return $this->lines->matching($sort);
    }

    public function getValidateDate(): ?\DateTimeInterface
    {
        return $this->validate_date;
    }

    public function setValidateDate(?\DateTimeInterface $validate_date): self
    {
        $this->validate_date = $validate_date;

        return $this;
    }

    public function getPeriod()
    {
        if($this->getStartDate() && $this->getStartDate() instanceof \DateTimeInterface)
        {
            $month = $this->getStartDate()->format('F');
            $year = $this->getStartDate()->format('Y');

            $fmt = new \IntlDateFormatter(
                'fr_FR',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                'Europe/Paris',
                \IntlDateFormatter::GREGORIAN,
                'LLLL'
            );
            
            $MonthFr = ucfirst($fmt->format(new \DateTime("first day of ".$month.' '.$year)));
            //dd($MonthFr);
            return $MonthFr.' '.$year;
        }
    }
    
    public function getYear(): ?\DateTimeInterface
    {
        return $this->year;
    }

    public function setYear(?\DateTimeInterface $year)
    {
        $this->year = $year;
    }
    
    public function setPeriod($period)
    {
        if($this->year){
            $this->start_date = new \DateTime("first day of ".$period." ".$this->year->format('Y'));
            $this->end_date = new \DateTime("last day of ".$period." ".$this->year->format('Y'));
        }else{
            $this->start_date = new \DateTime("first day of ".$period." this year");
            $this->end_date = new \DateTime("last day of ".$period." this year");
        }
    }

    /**
     * @return Collection|VehiculesReport[]
     */
    public function getVehiculesReports(): Collection
    {
        return $this->vehiculesReports;
    }

    public function addVehiculesReport(VehiculesReport $vehiculesReport): self
    {
        if (!$this->vehiculesReports->contains($vehiculesReport)) {
            $this->vehiculesReports[] = $vehiculesReport;
            $vehiculesReport->setReport($this);
        }

        return $this;
    }

    public function removeVehiculesReport(VehiculesReport $vehiculesReport): self
    {
        $this->vehiculesReports->removeElement($vehiculesReport);
        return $this;
    }

    public function getVehicules()
    {
        $vehicules = [];
        foreach($this->getLines() as $line)
        {
            $vehicules[$line->getVehicule()->getId()] = $line->getVehicule();
        }

        return $vehicules;
    }

    public function isVehiculeInVehiculeReport(Vehicule $vehicule)
    {
        foreach($this->getVehiculesReports() as $vehiculeReport)
        {
            if($vehiculeReport->getVehicule() == $vehicule){
                return $vehiculeReport;
            }
        }

        return false;
    }

    /*public function getVehiculesReportByYear($year)
    {
        $vehiculesReportsByYear = [];

        foreach($this->getVehiculesReports() as $vr)
        {
            $firstDay = new \DateTime("first day of January ".$year);
            $lastDay = new \DateTime("last day of December ".$year);

            if($vr->getReport()->getStartDate() >= $firstDay && $vr->getReport()->getEndDate() <= $lastDay)
            {
                $vehiculesReportsByYear[] = $vr;
            }
        }

        return $vehiculesReportsByYear;
    }*/

    public function getVehiculesReportsTotalKm()
    {
        $totalKm = 0;

        foreach($this->getVehiculesReports() as $vr)
        {
            $totalKm += $vr->getKm();
        }

        return $totalKm;
    }

    public function getVehiculesReportsTotalAmount()
    {
        $totalAmount = 0;

        foreach($this->getVehiculesReports() as $vr)
        {
            $totalAmount += $vr->getTotal();
        }

        return $totalAmount;
    }

    public function resetId()
    {
        $this->id = null;
    }

    public function synchronizeLines(): void
    {
        $targetYear = (int) $this->start_date->format('Y');
        $targetMonth = (int) $this->start_date->format('m');

        foreach ($this->getLines() as $line) {
            $travelDate = $line->getTravelDate();

            // Ne rien faire si déjà au bon mois/année
            if (
                $travelDate !== null &&
                (int) $travelDate->format('Y') === $targetYear &&
                (int) $travelDate->format('m') === $targetMonth
            ) {
                continue;
            }

            // Récupère le jour existant, ou "01" si null
            $originalDay = $travelDate?->format('d') ?? '01';
            $day = (int) $originalDay;

            // Calcul du dernier jour valide du mois
            $lastDayOfMonth = (int) (new \DateTimeImmutable("$targetYear-$targetMonth-01"))
                ->modify('last day of')
                ->format('d');

            // Clamp du jour pour éviter les dates invalides
            $safeDay = min($day, $lastDayOfMonth);

            // Création de la nouvelle date
            $newDate = \DateTime::createFromFormat('Y-m-d', sprintf(
                '%04d-%02d-%02d',
                $targetYear,
                $targetMonth,
                $safeDay
            ));

            if ($newDate !== false) {
                $line->setTravelDate($newDate);
            }
        }
    }

    public function getReportLinesForWeek(int $weekNumber): array
    {
        $out = [];
        foreach ($this->getLines() as $line) {
            if ((int) $line->getTravelDate()->format('W') === $weekNumber) {
                $out[] = $line;
            }
        }
        return $out;
    }

    public function getReportLineById(int $lineId): ?ReportLine
    {
        foreach ($this->getLines() as $line) {
            if ((int) $line->getId() === $lineId) {
                return $line;
            }
        }

        return null;
    }

    public function getMonth(): ?\DateTimeInterface
    {
        return $this->month;
    }

    public function setMonth(?\DateTimeInterface $month)
    {
        $this->month = $month;
    }

    public function getKm(): ?int
    {
        return $this->km;
    }

    public function setKm(?int $km): static
    {
        $this->km = $km;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(?string $total): static
    {
        $this->total = $total;

        return $this;
    }
    
    public function getLinesReportList(): string
    {
        return '';
    }
}
