<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Utils\InvoicePdf;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $invoice = Action::new('invoice', 'Invoice', 'fa fa-file-invoice')
            // renders the action as a <a> HTML element
            ->displayAsLink()
            ->linkToCrudAction('generateInvoicePdf');

        return $actions
            ->add(Crud::PAGE_INDEX,$invoice);
            ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere('entity.status = (:status)');
        $qb->setParameter('status', Order::STATUS_PAID);

        return $qb;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user'),
            AssociationField::new('Plan'),
            Field::new('createdAt')->onlyOnIndex(),
            Field::new('updatedAt')->onlyOnIndex(),
            Field::new('productName'),
            Field::new('productDescription'),
            Field::new('vatAmount'),
            Field::new('total_ht'),
            Field::new('status')->onlyOnIndex(),
        ];
    }

    public function generateInvoicePdf(AdminContext $context)
    {
        $order = $context->getEntity()->getInstance();

        if($this->isGranted('ROLE_ADMIN') || $this->getUser() == $order->getUser()){
            $pdf = new InvoicePdf();
            $pdfContent = $pdf->generatePdf($order);
            $filename = 'Mileo_Facture_'.$order->getInvoice()->getNum().'.pdf';
            $response = new Response($pdfContent);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
    
            return $response;
            
            
        }else{
            throw new AccessDeniedException();
        }
        
    }
}
