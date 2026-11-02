<?php

namespace App\Repository\gestion_switch;

use App\Entity\gestion_switch\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipement>
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    /**
     * Switchs et clusters Wi-Fi dans l'ordre d'affichage.
     *
     * @return Equipement[]
     */
    public function findOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.position', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
