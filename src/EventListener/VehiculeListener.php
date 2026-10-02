<?php

namespace App\EventListener;

use App\Entity\Vehicule;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class VehiculeListener
{
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if($entity instanceof Vehicule)
        {
            $this->atLeastOneDefaultVehicule($args);
        }
    }
    
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if($entity instanceof Vehicule)
        {
            $this->atLeastOneDefaultVehicule($args);
        }
    }

    public function atLeastOneDefaultVehicule(LifecycleEventArgs $args)
    {
        $entityManager = $args->getObjectManager();
        $entity = $args->getObject();
        $user = $entity->getUser();

        if($entity->getIsDefault() || !$user->getDefaultVehicule())
        {
            $vehicules = $user->getVehicules();
            foreach ($vehicules as $v) {
                $v->setIsDefault(false);
                $entityManager->persist($v);
            }

            $entity->setIsDefault(true);
        }

        $entityManager->persist($entity);
        $entityManager->flush();
    }

}
