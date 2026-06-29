<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Subscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method Subscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method Subscription[]    findAll()
 * @method Subscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findSubscriptionsToWarn(
        int $daysUntilExpiry,
        \DateTimeInterface $today
    ): array {
        $sentAtProperty = match ($daysUntilExpiry) {
            30 => 'warningMailThirtyDaysSentAt',
            7 => 'warningMailSevenDaysSentAt',
            default => throw new \InvalidArgumentException('Unsupported warning delay.'),
        };

        $today = \DateTimeImmutable::createFromInterface($today)->setTime(0, 0);
        $expiryStart = $today->modify(sprintf('+%d days', $daysUntilExpiry));
        $expiryEnd = $expiryStart->modify('+1 day');

        return $this->createQueryBuilder('subscription')
            ->select('subscription, subscriber, plan')
            ->innerJoin('subscription.user', 'subscriber')
            ->innerJoin('subscription.plan', 'plan')
            ->andWhere('subscription.subscription_start <= :today')
            ->andWhere(
                '(subscription.trialEndsAt IS NOT NULL
                    AND subscription.trialEndsAt >= :expiryStart
                    AND subscription.trialEndsAt < :expiryEnd)
                OR
                (subscription.trialEndsAt IS NULL
                    AND subscription.subscription_end >= :expiryStart
                    AND subscription.subscription_end < :expiryEnd)'
            )
            ->andWhere(sprintf('subscription.%s IS NULL', $sentAtProperty))
            ->andWhere(
                '(subscription.trialEndsAt IS NOT NULL
                    OR LOWER(plan.name) LIKE :proPlan
                    OR LOWER(plan.name) LIKE :teamPlan)'
            )
            ->andWhere(
                'subscription.trialEndsAt IS NULL OR :daysUntilExpiry = 7'
            )
            ->setParameter('daysUntilExpiry', $daysUntilExpiry)
            ->setParameter('today', $today)
            ->setParameter('expiryStart', $expiryStart)
            ->setParameter('expiryEnd', $expiryEnd)
            ->setParameter('proPlan', '%pro%')
            ->setParameter('teamPlan', '%team%')
            ->orderBy('subscription.subscription_end', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return Subscription[] Returns an array of Subscription objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
