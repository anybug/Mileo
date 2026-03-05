<?php

namespace App\Controller\App;

use App\Entity\Order;
use App\Utils\InvoicePdf;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OrderAppCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            
            ->setPageTitle('index', 'Invoices')
            
        ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere('entity.user = (:user)');
        $qb->setParameter('user', $this->getUser());
        $qb->andWhere('entity.status = (:status)');
        $qb->setParameter('status', Order::STATUS_PAID);
        return $qb;
    }

    public function configureActions(Actions $actions): Actions
    {
        $invoice = Action::new('invoice', 'Invoice', 'fa fa-file-invoice')
            // renders the action as a <a> HTML element
            ->displayAsLink()
            ->linkToCrudAction('generateInvoicePdf');

        return $actions
            ->disable(Action::NEW)
            ->disable(Action::EDIT)
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX,$invoice);
            
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateField::new('created_at','Date')->onlyOnIndex(),
            Field::new('InvoiceNum','Numéro'),
            Field::new('billingName','Nom'),
            Field::new('billingAddress','Adresse')->onlyOnIndex(),
            Field::new('billingPostcode','Code postal')->onlyOnIndex(),
            Field::new('billingCity','Ville')->onlyOnIndex(),
            Field::new('TotalHt','Total HT')->onlyOnIndex()->setTemplatePath("App/Fields/euroField.html.twig"),
            NumberField::new('vatAmount','Montant TVA')->onlyOnIndex()->setTemplatePath("App/Fields/euroField.html.twig"),
            Field::new('TotalTTC','Total TTC')->onlyOnIndex()->setTemplatePath("App/Fields/euroField.html.twig"),
        ];
    }

    public function successPayment(EntityManagerInterface $manager, AdminContext $context, MailerInterface $mailer)
    {
        
        $order = $manager->getRepository(Order::class)->find($context->getRequest()->query->get('id'));

        /*$email = (new TemplatedEmail())
        ->to(new Address($order->getUser()->getEmail()))
        ->subject('Votre abonnement Mileo')
        ->htmlTemplate('Emails/order.html.twig')
        ->context([
            'order' => $order,
        ]);
        
        $mailer->send($email);*/

        if (isset($order) && $order->getUser() == $this->getUser()) {
            return $this->render('App/Payment/order-success.html.twig',['order' => $order]);
        }
        else{
            return $this->render('App/error404.html.twig');
        }
    }
    
    #[AdminRoute(path: '/invoice/download', name: 'app_generate_invoice')]
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
