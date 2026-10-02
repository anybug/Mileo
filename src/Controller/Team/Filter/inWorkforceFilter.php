<?php

namespace App\Controller\Team\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class inWorkforceFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions([
                'choices' => [
                    'Oui' => 'yes',
                    'Non' => 'no',
                    'Oui et Non' => 'both',
                ],
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        $alias = $filterDataDto->getEntityAlias();

        if ($value === 'yes') {
            $queryBuilder->andWhere(sprintf('%s.workforceExitDate IS NULL', $alias));
        } elseif ($value === 'no') {
            $queryBuilder->andWhere(sprintf('%s.workforceExitDate IS NOT NULL', $alias));
        } elseif ($value === 'both') {
            $queryBuilder->andWhere(sprintf('%s.workforceExitDate IS NOT NULL OR %s.workforceExitDate IS NULL', $alias, $alias));
        }
    }
}