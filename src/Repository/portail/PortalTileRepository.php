<?php

namespace App\Repository\portail;

use App\Entity\portail\PortalTile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PortalTile>
 */
class PortalTileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortalTile::class);
    }

    /**
     * @return PortalTile[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.actif = true')
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PortalTile[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
