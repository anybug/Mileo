<?php

namespace App\Repository;

use App\Entity\News;
use App\Enum\NewsScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 *
 * @method News|null find($id, $lockMode = null, $lockVersion = null)
 * @method News|null findOneBy(array $criteria, array $orderBy = null)
 * @method News[]    findAll()
 * @method News[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    /**
     * Retourne les dernières actualités dont la date de publication
     * est passée.
     *
     * @return list<News>
     */
    public function findLatestPublished(?NewsScope $scope = NewsScope::ALL, ?int $limit = 3): array
    {
        $q = $this->createQueryBuilder('n')
            ->where('n.isPublished = :published')
            ->setParameter('published', true)
            ->andWhere('n.scope IN (:scopes)')
            ->orderBy('n.publishedAt', 'DESC')
            ->setMaxResults($limit)
        ;

        if($scope !== null and $scope instanceof NewsScope)
        {
            $scopeParameters = [NewsScope::ALL->value, $scope->value];
        }else{
            $scopeParameters = [NewsScope::ALL->value];
        }

        $q->setParameter('scopes', $scopeParameters);

        return $q
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return News[] Returns an array of News objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('n.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?News
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
