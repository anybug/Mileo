<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\User;
use App\Repository\PlanRepository;

/**
 * Résout le plan qui régit un utilisateur à un instant donné.
 *
 * Ce service vit ici (et non sur l'entité User) parce que le repli vers FREE
 * a besoin de PlanRepository, et une entité ne doit pas dépendre d'un repository.
 *
 * À ne pas confondre avec User::hasValidSubscription(), qui reste un booléen
 * pur graphe d'objets sur l'entité (utilisé par les gates canAdd/canUse*).
 */
final class PlanResolver
{
    public function __construct(private readonly PlanRepository $plans)
    {
    }

    /**
     * Priorité :
     *   1. abonnement personnel valide (un Pro qui paie lui-même, en essai ou payé) ;
     *   2. droits hérités du manager (Team / Cabinet / portage), via managed_by ;
     *   3. socle FREE.
     */
    public function resolve(User $user, ?\DateTimeImmutable $at = null): Plan
    {
        $own = $user->getSubscription();
        if ($own !== null && $own->isValid($at)) {
            return $own->getPlan();
        }

        $manager = $user->getManagedBy();
        if ($manager !== null) {
            $managerSub = $manager->getSubscription();
            if ($managerSub !== null && $managerSub->isValid($at)) {
                return $managerSub->getPlan();
            }
        }

        return $this->plans->getFree();
    }
}
