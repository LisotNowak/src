<?php

namespace App\Repository\gestion_switch;

use App\Entity\gestion_switch\Vlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Les lectures et écritures en nombre passent par App\Service\Aruba\Store (SQL direct).
 *
 * @extends ServiceEntityRepository<Vlan>
 */
class VlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vlan::class);
    }
}
