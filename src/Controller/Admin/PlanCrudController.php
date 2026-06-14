<?php

namespace App\Controller\Admin;

use App\Entity\Plan;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PlanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Plan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            Field::new('name'),
            Field::new('price_per_year'),
            Field::new('plan_period'),
            Field::new('billingDetails'),
            Field::new('old_price_per_year'),
            Field::new('description'),
            
        ];
    }
}
