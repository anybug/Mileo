<?php

namespace App\Repository;

use App\Entity\UserAddress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;

/**
 * @method UserAddress|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserAddress|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserAddress[]    findAll()
 * @method UserAddress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserAddressRepository extends ServiceEntityRepository
{
    private $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        $this->security = $security;
        parent::__construct($registry, UserAddress::class);
    }


    public function dashboardQuery()
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :val')
            ->setParameter('val', $this->security->getUser())
            ->getQuery()
            ->getResult()
        ;
    }

    public function countAddressManagedBy(): int
    {
        $me = $this->security->getUser();

        if (!$me instanceof User) {
            return 0;
        }

        return (int) $this->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->andWhere('entity.user = :user')
            ->setParameter('user', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // /**
    //  * @return UserAddress[] Returns an array of UserAddress objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UserAddress
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
