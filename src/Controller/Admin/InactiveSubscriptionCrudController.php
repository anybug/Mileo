<?php

namespace App\Controller\Admin;

use App\Entity\Subscription;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class InactiveSubscriptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Subscription::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityPermission('ROLE_ADMIN')
            ->setEntityLabelInSingular('Abonnement inactif')
            ->setEntityLabelInPlural('Abonnements inactifs')
            ->setPageTitle(Crud::PAGE_INDEX, 'Abonnements inactifs')
            ->setDefaultSort(['subscription_end' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('user', 'Utilisateur')
            ->autocomplete();

        yield AssociationField::new('plan', 'Offre');

        yield DateTimeField::new('subscription_start', 'Début');
        yield DateTimeField::new('subscription_end', 'Expiration');

        yield DateTimeField::new('warningMailSentAt', 'Avertissement envoyé le')
            ->onlyOnDetail();

        yield DateTimeField::new('expiredMailSentAt', 'Expiration envoyée le')
            ->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('user')
            ->add('plan')
            ->add(DateTimeFilter::new('subscription_end', 'Date d’expiration'));
    }

    public function createIndexQueryBuilder(
        SearchDto $search,
        EntityDto $entity,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $now = new \DateTimeImmutable();

        $queryBuilder = parent::createIndexQueryBuilder(
            $search,
            $entity,
            $fields,
            $filters
        );

        return $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->orX(
                    'entity.subscription_start > :now',
                    'entity.subscription_end <= :now'
                )
            )
            ->setParameter('now', $now);
    }
}