<?php

namespace App\Controller\App\Filter;

use App\Controller\App\Filter\LineDateFilterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Security\Core\Security;

class ReportYearFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ReportYearFilterType::class)
            ;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $choice = $filterDataDto->getValue();
        $choices = explode( ' -> ', $choice);
        $firstDay = new \DateTime("first day of ".$choices[0]);
        $lastDay = new \DateTime("last day of ".$choices[1]);
        // dd($lastDay);
        $queryBuilder->andWhere("entity.start_date >= (:firstday)");
        $queryBuilder->andWhere("entity.end_date <= (:lastday)");
        $queryBuilder->setParameter('firstday', $firstDay);
        $queryBuilder->setParameter('lastday', $lastDay);
    }
}