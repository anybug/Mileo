<?php

namespace App\Form\Filter;

use App\Form\SubscriptionExpiryFilterType;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;

final class SubscriptionExpiryFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(
        string $propertyName = 'subscription_end',
        ?string $label = null
    ): self {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(SubscriptionExpiryFilterType::class);
    }

    public function apply(
        QueryBuilder $queryBuilder,
        FilterDataDto $filterDataDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto
    ): void {
        $days = $filterDataDto->getValue();

        if (!is_string($days) || !in_array($days, ['30', '7', '0'], true)) {
            return;
        }

        $daysUntilExpiry = (int) $days;
        $expiryStart = new \DateTimeImmutable('today');
        $expiryEnd = $expiryStart->modify(sprintf('+%d days', $daysUntilExpiry + 1));

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();

        $queryBuilder
            ->andWhere(sprintf('%s.%s >= :expiryStart', $alias, $property))
            ->andWhere(sprintf('%s.%s < :expiryEnd', $alias, $property))
            ->setParameter('expiryStart', $expiryStart)
            ->setParameter('expiryEnd', $expiryEnd);
    }
}