<?php

namespace App\Form\Filter;

use App\Form\SubscriptionInactiveSinceFilterType;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;

final class SubscriptionInactiveSinceFilter implements FilterInterface
{
    use FilterTrait;

    private const ALLOWED_VALUES = [
        '0',
        '7',
        '30',
        'older_than_30',
    ];

    public static function new(
        string $propertyName = 'subscription_end',
        ?string $label = null
    ): self {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(SubscriptionInactiveSinceFilterType::class);
    }

    public function apply(
        QueryBuilder $queryBuilder,
        FilterDataDto $filterDataDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto
    ): void {
        $period = $filterDataDto->getValue();

        if (
            !is_string($period)
            || !in_array($period, self::ALLOWED_VALUES, true)
        ) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();

        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        /*
         * Tous les choix, sauf "plus de 30 jours", correspondent à une
         * période comprise entre une date passée et la fin de la journée.
         */
        if ($period !== 'older_than_30') {
            $days = (int) $period;
            $inactiveStart = $today->modify(sprintf('-%d days', $days));

            $queryBuilder
                ->andWhere(sprintf(
                    '%s.%s >= :inactiveStart',
                    $alias,
                    $property
                ))
                ->andWhere(sprintf(
                    '%s.%s < :inactiveEnd',
                    $alias,
                    $property
                ))
                ->setParameter('inactiveStart', $inactiveStart)
                ->setParameter('inactiveEnd', $tomorrow);

            return;
        }

        $inactiveBefore = $today->modify('-30 days');

        $queryBuilder
            ->andWhere(sprintf(
                '%s.%s < :inactiveBefore',
                $alias,
                $property
            ))
            ->setParameter('inactiveBefore', $inactiveBefore);
    }
}