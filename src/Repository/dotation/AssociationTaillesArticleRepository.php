<?php

namespace App\Repository\dotation;

use App\Entity\dotation\AssociationTaillesArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssociationTaillesArticle>
 */
class AssociationTaillesArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssociationTaillesArticle::class);
    }

    public function findAllForDotation(): array
    {
        return $this->createQueryBuilder('ata')
            ->leftJoin('ata.article', 'a')->addSelect('a')
            ->leftJoin('ata.taille', 'ta')->addSelect('ta')
            ->orderBy('ata.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
