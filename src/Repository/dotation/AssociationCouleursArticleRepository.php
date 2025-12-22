<?php

namespace App\Repository\dotation;

use App\Entity\dotation\AssociationCouleursArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssociationCouleursArticle>
 */
class AssociationCouleursArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssociationCouleursArticle::class);
    }

    public function findAllForDotation(): array
    {
        return $this->createQueryBuilder('aca')
            ->leftJoin('aca.article', 'a')->addSelect('a')
            ->leftJoin('aca.couleur', 'co')->addSelect('co')
            ->orderBy('aca.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
