<?php

namespace App\Repository\dotation;

use App\Entity\dotation\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Chargement optimisé pour la page dotation
     * Évite complètement le N+1 pour tailles/couleurs
     */
    public function findAllForDotation(): array
    {
        return $this->createQueryBuilder('a')
            // tailles
            ->leftJoin('a.associationTailles', 'ata')->addSelect('ata')
            ->leftJoin('ata.taille', 'ta')->addSelect('ta')

            // couleurs
            ->leftJoin('a.associationCouleurs', 'aca')->addSelect('aca')
            ->leftJoin('aca.couleur', 'co')->addSelect('co')

            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
