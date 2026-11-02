<?php

namespace App\Repository\gestion_switch;

use App\Entity\gestion_switch\CollecteEquipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Les lectures et écritures en nombre passent par App\Service\Aruba\Store (SQL direct).
 *
 * @extends ServiceEntityRepository<CollecteEquipement>
 */
class CollecteEquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollecteEquipement::class);
    }
}
