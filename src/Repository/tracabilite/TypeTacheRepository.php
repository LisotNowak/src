<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\TypeTache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TypeTacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeTache::class);
    }

    /** Tous les types triés par nom, avec leurs équipes */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('ty')
            ->leftJoin('ty.equipes', 'e')->addSelect('e')
            ->orderBy('ty.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
