<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\News;
use App\Form\QuillEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

#[IsGranted('ROLE_ADMIN')]
final class NewsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return News::class;
    } 

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityPermission('ROLE_ADMIN')
            ->setEntityLabelInSingular('Actualité')
            ->setEntityLabelInPlural('Actualités')
            ->setPageTitle(Crud::PAGE_INDEX, 'Actualités')
            ->setPageTitle(Crud::PAGE_NEW, 'Créer une actualité')
            ->setPageTitle(Crud::PAGE_EDIT, fn (News $news) => 'Modifier : '.$news->getTitle())
            ->setPageTitle(Crud::PAGE_DETAIL, fn (News $news) => $news->getTitle() ?? 'Actualité')
            ->setDefaultSort([
                'publishedAt' => 'DESC',
            ])
            ->setSearchFields([
                'title',
                'content',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('title', 'Titre');

            yield BooleanField::new('isPublished', 'Publiée');  

            yield TextareaField::new('content', 'Contenu')
                ->setTemplatePath('Admin/News/Fields/content.html.twig');

            yield ImageField::new('photo', 'Photo')
                ->setBasePath('uploads/members/photos')                // URL publique
                ->hideOnForm();

            yield DateTimeField::new('publishedAt', 'Date de parution')
                ->setFormat('dd/MM/yyyy');

            yield DateTimeField::new('createdAt', 'Créée le')
                ->setFormat('dd/MM/yyyy');

            yield DateTimeField::new('updatedAt', 'Modifiée le')
                ->setFormat('dd/MM/yyyy');

            return;
        }

        if ($pageName === Crud::PAGE_INDEX) {

            yield TextField::new('title', 'Titre');

            yield ImageField::new('poster', 'Illustration')
                ->setBasePath('uploads/news/poster')
                ->hideOnForm()
            ;

            yield BooleanField::new('isPublished', 'Publiée');

            yield DateTimeField::new('publishedAt', 'Date de parution')
                ->setFormat('dd/MM/yyyy');

            yield DateTimeField::new('createdAt', 'Créée le')
                ->setFormat('dd/MM/yyyy');

            yield DateTimeField::new('updatedAt', 'Modifiée le')
                ->setFormat('dd/MM/yyyy');

            return;
        }

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {

            yield TextField::new('title', 'Titre')
                ->setColumns(12);

            yield BooleanField::new('isPublished', 'Publier l\'actualité')
                ->renderAsSwitch(true)
                ->setColumns(12);

            yield TextField::new('posterFile', 'Image d\'illustration')
                ->setFormType(VichImageType::class)
                ->onlyOnForms()
                ->setFormTypeOptions([
                    'required' => $pageName === Crud::PAGE_NEW ?? false,
                    'allow_delete' => true,
                    'download_uri' => false,
                    'image_uri' => true,
                ]);

            yield TextareaField::new('content', 'Contenu')
                ->setFormType(QuillEditorType::class)
                ->setFormTypeOption('attr', [
                    'data-controller' => 'quill-editor',
                    'data-quill-editor-upload-url-value' => $this->generateUrl('admin_news_upload_content_file'),
                ])
                ->setTemplatePath('admin/internal_communication/field/content.html.twig')
                ->setColumns(12);

            yield DateTimeField::new('publishedAt', 'Date de parution')
                ->setFormat('dd/MM/yyyy');


            return;
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->enforceMaxPublishedNews($entityManager, $entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->enforceMaxPublishedNews($entityManager, $entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function enforceMaxPublishedNews(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof News || !$entityInstance->isPublished()) {
            return;
        }

        $repository = $entityManager->getRepository(News::class);
        $publishedNews = $repository->findBy(
            ['isPublished' => true],
            ['publishedAt' => 'DESC']
        );

        $otherPublishedNews = array_filter($publishedNews, fn($news) => $news !== $entityInstance);

        if (count($otherPublishedNews) >= 3) {
            array_shift($otherPublishedNews);

            foreach ($otherPublishedNews as $oldNews) {
                $oldNews->setIsPublished(false);
                $entityManager->persist($oldNews);
            }
        }
    }
}