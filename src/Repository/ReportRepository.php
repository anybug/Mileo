<?php

namespace App\Repository;

use App\Entity\Report;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;

/**
 * @method Report|null find($id, $lockMode = null, $lockVersion = null)
 * @method Report|null findOneBy(array $criteria, array $orderBy = null)
 * @method Report[]    findAll()
 * @method Report[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReportRepository extends ServiceEntityRepository
{
    private $security;
    
    public function __construct(ManagerRegistry $registry, Security $security)
    {
        $this->security = $security;
        parent::__construct($registry, Report::class);
    }

    
    public function getReportsForUser()
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :val')
            ->setParameter('val', $this->security->getUser())
            ->orderBy('r.start_date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getLastReportForUser()
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = (:user)')
            ->setParameter('user', $this->security->getUser())
            ->orderBy('r.start_date', 'DESC')
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult()
        ;
    }

    public function findByYear($year, $user=false)
    {
        $firstDay = new \DateTime("first day of January ".$year);
        $lastDay = new \DateTime("last day of December ".$year);

        if(!$user)
        {
            $user = $this->security->getUser();
        }
        
        return $this->createQueryBuilder('r')
            ->andWhere("r.start_date >= (:firstday)")
            ->andWhere("r.end_date <= (:lastday)")
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->setParameter('firstday', $firstDay)
            ->setParameter('lastday', $lastDay)
            ->orderBy('r.start_date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    
    public function findByPeriod($start,$end)
    {
        $firstDay = new \DateTime("first day of ".$start);
        $lastDay = new \DateTime("last day of ".$end);
        
        return $this->createQueryBuilder('r')
            ->andWhere("r.start_date >= (:firstday)")
            ->andWhere("r.end_date <= (:lastday)")
            ->andWhere('r.user = :user')
            ->setParameter('user', $this->security->getUser())
            ->setParameter('firstday', $firstDay)
            ->setParameter('lastday', $lastDay)
            ->orderBy('r.start_date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    
    public function findByYearAndMonth($year,$month)
    {
        $firstDay = new \DateTime("first day of ".$month." ".$year);
        $lastDay = new \DateTime("last day of ".$month." ".$year);
        
        return $this->createQueryBuilder('r')
            ->andWhere("r.start_date = (:firstday)")
            ->andWhere("r.end_date = (:lastday)")
            ->andWhere('r.user = :user')
            ->setParameter('user', $this->security->getUser())
            ->setParameter('firstday', $firstDay)
            ->setParameter('lastday', $lastDay)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    
    public function findForPdf($year)
    {
        $firstDay = new \DateTime("first day of January ".$year);
        $lastDay = new \DateTime("last day of December ".$year);
        
        return $this->createQueryBuilder('r')
            ->andWhere("r.start_date >= (:firstday)")
            ->andWhere("r.end_date <= (:lastday)")
            ->andWhere('r.user = :user')
            ->setParameter('user', $this->security->getUser())
            ->setParameter('firstday', $firstDay)
            ->setParameter('lastday', $lastDay)
            ->orderBy('r.start_date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    
}
