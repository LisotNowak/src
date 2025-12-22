<?php

namespace App\Repository\dotation;

use App\Entity\dotation\AssociationCommandeArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssociationCommandeArticle>
 */
class AssociationCommandeArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssociationCommandeArticle::class);
    }

    public function findAllForDotation(): array
    {
        return $this->createQueryBuilder('aca')
            ->leftJoin('aca.article', 'a')->addSelect('a')
            ->leftJoin('aca.taille', 'ta')->addSelect('ta')
            ->leftJoin('aca.couleur', 'co')->addSelect('co')
            ->leftJoin('aca.commande', 'c')->addSelect('c')
            ->orderBy('aca.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
