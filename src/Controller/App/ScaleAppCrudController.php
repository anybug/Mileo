<?php

namespace App\Controller\App;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;

use App\Entity\Scale;

class ScaleAppCrudController extends AbstractCrudController
{

    public static function getEntityFqcn(): string
    {
        return Scale::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
        ->overrideTemplate('crud/index', 'App/Scale/index.html.twig')
        ->setPageTitle('index', 'Barèmes kilométriques en vigueur')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // ...
            // this will forbid to create or delete entities in the backend
            ->disable(Action::NEW, Action::DELETE, Action::EDIT)
        ;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $context = $this->getContext();

        $newResponseParameters = parent::configureResponseParameters($responseParameters);

        $pageName = $newResponseParameters->get('pageName');
        if($pageName != Crud::PAGE_INDEX){
            throw new ForbiddenActionException($context);
        }

        $em = $this->container->get('doctrine')->getManagerForClass($this->getEntityFqcn());

        $sorted_scales = $car_scales = $bike_scales = [];
        
        $scales = $em->getRepository(Scale::class)->findAll(); 
        foreach($scales as $s)
        {
            $sorted_scales[$s->getYear()][] = $s;
        }

        //retrieve latest Scale only
        ksort($sorted_scales);
        $latest_scales = array_pop($sorted_scales);

        //cars
        foreach($latest_scales as $s)
        {
            if($s->getPower()->getType()=='Car'){
                $car_scales[(string) $s->__toStringWithoutAmount()][(string) $s->getPower()->__toString()] = $s->__toStringAmountOnly();
            }elseif($s->getPower()->getType()=='Cyclo'){
                $bike_scales[(string) $s->__toStringWithoutAmount()][(string) $s->getPower()->__toString()] = $s->__toStringAmountOnly();
            }
        }

        $parameters = [
            'car_scales' => $car_scales,
            'bike_scales' => $bike_scales,
        ];

        $responseParameters->setAll($parameters);

        return $newResponseParameters;
    }


}

?>