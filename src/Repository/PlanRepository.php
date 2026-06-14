<?php

namespace App\Repository;

use App\Entity\Plan;
use App\Enum\PlanCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Plan|null find($id, $lockMode = null, $lockVersion = null)
 * @method Plan|null findOneBy(array $criteria, array $orderBy = null)
 * @method Plan[]    findAll()
 * @method Plan[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    public function findByCode(PlanCode $code): ?Plan
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Le socle de repli. Doit toujours exister en base
     * pour que PlanResolver le renvoie par défaut
     */
    public function getFree(): Plan
    {
        $free = $this->findByCode(PlanCode::FREE);

        if ($free === null) {
            throw new \RuntimeException(
                'Le plan FREE est introuvable : il doit exister en base comme socle de repli.'
            );
        }

        return $free;
    }

    /**
     * Plans publiés, ordonnés pour la grille tarifaire.
     *
     * @return Plan[]
     */
    public function findPublishedOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->orderBy('p.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

}   
