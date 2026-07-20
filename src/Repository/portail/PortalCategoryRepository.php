<?php

namespace App\Repository\portail;

use App\Entity\portail\PortalCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PortalCategory>
 */
class PortalCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortalCategory::class);
    }

    /**
     * @return PortalCategory[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catégories actives, avec uniquement leurs tuiles actives, triées.
     *
     * @return PortalCategory[]
     */
    public function findActiveWithActiveTiles(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('t')
            ->leftJoin('c.tiles', 't', 'WITH', 't.actif = true')
            ->andWhere('c.actif = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
