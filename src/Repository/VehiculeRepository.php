<?php

namespace App\Repository;

use App\Entity\Vehicule;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\QueryBuilder;

/**
 * @method Vehicule|null find($id, $lockMode = null, $lockVersion = null)
 * @method Vehicule|null findOneBy(array $criteria, array $orderBy = null)
 * @method Vehicule[]    findAll()
 * @method Vehicule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VehiculeRepository extends ServiceEntityRepository
{
    private $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        $this->security = $security;
        parent::__construct($registry, Vehicule::class);
    }


    public function dashboardQuery()
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user = :val')
            ->setParameter('val', $this->security->getUser())
            ->getQuery()
            ->getResult()
        ;
    }

    public function countVehiculesManagedBy(): int
    {
        $me = $this->security->getUser();

        if (!$me instanceof User) {
            return 0;
        }

        return (int) $this->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->andWhere('entity.user IN (:users)')
            ->setParameter('users', $me->getMembers())
            ->getQuery()
            ->getSingleScalarResult();
    }

    // /**
    //  * @return Vehicule[] Returns an array of Vehicule objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Vehicule
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
